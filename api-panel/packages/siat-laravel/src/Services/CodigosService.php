<?php

namespace Misalud\Siat\Services;

use Illuminate\Support\Facades\Cache;
use Misalud\Siat\Exceptions\SiatException;
use Misalud\Siat\Utils\SoapWrapper;

/**
 * CodigosService
 *
 * Gestiona la obtención y cacheo de los códigos que el SIAT exige
 * antes de poder emitir facturas:
 *
 * - CUIS: Código Único de Inicio de Sistemas (se solicita por modalidad/punto de venta)
 * - CUFD: Código Único de Facturación Diaria (válido 24h, requiere CUIS)
 *
 * Ambos códigos se cachean localmente para evitar peticiones innecesarias al SIAT.
 */
class CodigosService
{
    protected SoapWrapper $soap;
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->soap   = new SoapWrapper($config['wsdl']['codigos'], $config);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CUIS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Solicita el CUIS al SIAT (o lo obtiene del caché si aún es válido).
     *
     * @return string  El código CUIS
     * @throws SiatException
     */
    public function obtenerCuis(): string
    {
        $cacheKey = $this->claveCacheCuis();

        return Cache::remember($cacheKey, $this->config['cache']['ttl_cuis'] * 60, function () {
            return $this->solicitarCuisAlSiat();
        });
    }

    /**
     * Fuerza una nueva solicitud de CUIS al SIAT, ignorando el caché.
     */
    public function renovarCuis(): string
    {
        $cuis = $this->solicitarCuisAlSiat();
        Cache::put($this->claveCacheCuis(), $cuis, $this->config['cache']['ttl_cuis'] * 60);
        return $cuis;
    }

    protected function solicitarCuisAlSiat(): string
    {
        $params = [
            'SolicitudCuis' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoPuntoVenta' => $this->config['punto_venta'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
            ],
        ];

        $response = $this->soap->call('cuis', $params);

        // Validar la respuesta del SIAT
        $resultado = $response['RespuestaCuis'] ?? null;

        if (!$resultado || empty($resultado['codigo'])) {
            $mensajes = $this->extraerMensajes($resultado);
            throw SiatException::transaccionRechazada(
                "No se pudo obtener CUIS. {$mensajes}"
            );
        }

        return $resultado['codigo'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CUFD
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Solicita el CUFD al SIAT (o lo obtiene del caché si aún es válido).
     * El CUFD incluye el CodigoControl necesario para generar el CUF.
     *
     * @return array ['codigo' => string, 'codigoControl' => string, 'fechaVigencia' => string]
     * @throws SiatException
     */
    public function obtenerCufd(): array
    {
        $cacheKey = $this->claveCacheCufd();

        return Cache::remember($cacheKey, $this->config['cache']['ttl_cufd'] * 60, function () {
            return $this->solicitarCufdAlSiat();
        });
    }

    /**
     * Fuerza renovación del CUFD (al inicio del día o por vencimiento).
     */
    public function renovarCufd(): array
    {
        $cufd = $this->solicitarCufdAlSiat();
        Cache::put($this->claveCacheCufd(), $cufd, $this->config['cache']['ttl_cufd'] * 60);
        return $cufd;
    }

    protected function solicitarCufdAlSiat(): array
    {
        // CUFD requiere un CUIS válido
        $cuis = $this->obtenerCuis();

        $params = [
            'SolicitudCufd' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoPuntoVenta' => $this->config['punto_venta'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap->call('cufd', $params);

        $resultado = $response['RespuestaCufd'] ?? null;

        if (!$resultado || empty($resultado['codigo'])) {
            $mensajes = $this->extraerMensajes($resultado);
            throw SiatException::transaccionRechazada(
                "No se pudo obtener CUFD. {$mensajes}"
            );
        }

        return [
            'codigo'         => $resultado['codigo'],
            'codigoControl'  => $resultado['codigoControl'],
            'fechaVigencia'  => $resultado['fechaVigencia'] ?? null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilidades
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verifica la comunicación con el servicio de Códigos del SIAT.
     */
    public function verificarComunicacion(): bool
    {
        try {
            $response = $this->soap->call('verificarComunicacion', []);
            return ($response['RespuestaComunicacion']['transaccion'] ?? false) === true;
        } catch (SiatException) {
            return false;
        }
    }

    /**
     * Verifica si un NIT está activo ante el SIN.
     */
    public function verificarNit(int $nitAVerificar): bool
    {
        $cuis   = $this->obtenerCuis();
        $params = [
            'SolicitudVerificarNit' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoPuntoVenta' => $this->config['punto_venta'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
                'nitParaVerificacion' => $nitAVerificar,
            ],
        ];

        $response  = $this->soap->call('verificarNit', $params);
        $resultado = $response['RespuestaVerificarNit'] ?? [];

        return (bool) ($resultado['transaccion'] ?? false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Claves de caché (únicas por NIT + sucursal + modalidad)
    // ─────────────────────────────────────────────────────────────────────────

    protected function claveCacheCuis(): string
    {
        return sprintf(
            'siat_cuis_%s_%s_%s',
            $this->config['nit'],
            $this->config['sucursal'],
            $this->config['modalidad']
        );
    }

    protected function claveCacheCufd(): string
    {
        return sprintf(
            'siat_cufd_%s_%s_%s_%s',
            $this->config['nit'],
            $this->config['sucursal'],
            $this->config['punto_venta'],
            date('Ymd') // El CUFD cambia cada día
        );
    }

    protected function extraerMensajes(?array $resultado): string
    {
        if (empty($resultado['mensajesList'])) {
            return '';
        }

        $mensajesList = $resultado['mensajesList'];
        $msgs = [];

        if (isset($mensajesList['codigo'])) {
            // Un solo mensaje directo (ej: ['codigo' => 902, 'descripcion' => '...'])
            $msgs[] = "[{$mensajesList['codigo']}] " . ($mensajesList['descripcion'] ?? '');
        } elseif (isset($mensajesList['MensajeServicio'])) {
            // Contiene una lista de mensajes bajo 'MensajeServicio'
            $rawMsgs = $mensajesList['MensajeServicio'];
            $rawMsgs = is_array($rawMsgs[0] ?? null) ? $rawMsgs : [$rawMsgs];
            foreach ($rawMsgs as $m) {
                if (isset($m['codigo'])) {
                    $msgs[] = "[{$m['codigo']}] " . ($m['descripcion'] ?? '');
                }
            }
        } elseif (is_array($mensajesList)) {
            // Una lista plana de mensajes o de arrays
            $rawMsgs = is_array($mensajesList[0] ?? null) ? $mensajesList : [$mensajesList];
            foreach ($rawMsgs as $m) {
                if (is_array($m) && isset($m['codigo'])) {
                    $msgs[] = "[{$m['codigo']}] " . ($m['descripcion'] ?? '');
                }
            }
        }

        return implode(' | ', $msgs);
    }
}

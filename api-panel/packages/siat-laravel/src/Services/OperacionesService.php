<?php

namespace Misalud\Siat\Services;

use Misalud\Siat\Exceptions\SiatException;
use Misalud\Siat\Utils\SoapWrapper;

/**
 * OperacionesService
 *
 * Maneja operaciones del sistema SIAT de Impuestos Nacionales de Bolivia:
 * - Registro, consulta y cierre de Puntos de Venta (POS)
 * - Registro y consulta de Eventos Significativos
 * - Cierre de Operaciones del Sistema
 */
class OperacionesService
{
    protected SoapWrapper $soap;
    protected CodigosService $codigos;
    protected array $config;

    public function __construct(array $config, CodigosService $codigos)
    {
        $this->config  = $config;
        $this->codigos = $codigos;
        $this->soap    = new SoapWrapper($config['wsdl']['operaciones'], $config);
    }

    /**
     * Registra un nuevo Punto de Venta (POS).
     *
     * @param int    $codigoTipoPuntoVenta Tipo de Punto de Venta (ej: 2 = Ventanilla de Cobranza, 5 = Cajero)
     * @param string $nombrePuntoVenta     Nombre identificativo del Punto de Venta
     * @param string $descripcion          Breve descripción
     * @return int                         Código del Punto de Venta registrado
     * @throws SiatException
     */
    public function registroPuntoVenta(int $codigoTipoPuntoVenta, string $nombrePuntoVenta, string $descripcion = ''): int
    {
        $cuis = $this->codigos->obtenerCuis();

        $params = [
            'SolicitudRegistroPuntoVenta' => [
                'codigoAmbiente'       => $this->config['ambiente'],
                'codigoModalidad'      => $this->config['modalidad'],
                'codigoSistema'        => $this->config['codigo_sistema'],
                'codigoSucursal'       => $this->config['sucursal'],
                'codigoTipoPuntoVenta' => $codigoTipoPuntoVenta,
                'cuis'                 => $cuis,
                'descripcion'          => $descripcion,
                'nit'                  => $this->config['nit'],
                'nombrePuntoVenta'     => $nombrePuntoVenta,
            ],
        ];

        $response  = $this->soap->call('registroPuntoVenta', $params);
        $resultado = $response['RespuestaRegistroPuntoVenta'] ?? [];

        if (empty($resultado['transaccion'])) {
            $mensajes = $this->extraerMensajes($resultado);
            throw SiatException::transaccionRechazada("Error al registrar punto de venta: {$mensajes}");
        }

        return (int) ($resultado['codigoPuntoVenta'] ?? 0);
    }

    /**
     * Cierra un Punto de Venta registrado.
     *
     * @param int $codigoPuntoVenta Código del Punto de Venta a cerrar
     * @return bool
     * @throws SiatException
     */
    public function cierrePuntoVenta(int $codigoPuntoVenta): bool
    {
        $cuis = $this->codigos->obtenerCuis();

        $params = [
            'SolicitudCierrePuntoVenta' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoPuntoVenta' => $codigoPuntoVenta,
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'cuis'             => $cuis,
                'nit'              => $this->config['nit'],
            ],
        ];

        $response  = $this->soap->call('cierrePuntoVenta', $params);
        $resultado = $response['RespuestaCierrePuntoVenta'] ?? [];

        if (empty($resultado['transaccion'])) {
            $mensajes = $this->extraerMensajes($resultado);
            throw SiatException::transaccionRechazada("Error al cerrar punto de venta: {$mensajes}");
        }

        return true;
    }

    /**
     * Consulta los Puntos de Venta registrados para la sucursal actual.
     *
     * @return array Lista de puntos de venta
     * @throws SiatException
     */
    public function consultaPuntoVenta(): array
    {
        $cuis = $this->codigos->obtenerCuis();

        $params = [
            'SolicitudConsultaPuntoVenta' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'cuis'             => $cuis,
                'nit'              => $this->config['nit'],
            ],
        ];

        $response  = $this->soap->call('consultaPuntoVenta', $params);
        $resultado = $response['RespuestaConsultaPuntoVenta'] ?? [];

        if (empty($resultado['transaccion'])) {
            $mensajes = $this->extraerMensajes($resultado);
            throw SiatException::transaccionRechazada("Error al consultar puntos de venta: {$mensajes}");
        }

        $lista = $resultado['listaPuntosVentas'] ?? [];

        // Asegurar estructura consistente
        if (isset($lista['codigoPuntoVenta'])) {
            // Un solo punto de venta devuelto como objeto stdClass
            return [$lista];
        }

        return $lista;
    }

    /**
     * Registra un Evento Significativo ante el SIAT (ej: caída de internet, corte de energía).
     *
     * @param int    $codigoMotivo Motivo del evento (catálogo SIAT)
     * @param string $descripcion  Detalle o explicación del evento
     * @param string $fechaInicio  Fecha y hora de inicio (formato Y-m-d\TH:i:s.v)
     * @param string $fechaFin     Fecha y hora de fin (formato Y-m-d\TH:i:s.v)
     * @return string              Código de recepción del evento significativo asignado por el SIAT
     * @throws SiatException
     */
    public function registroEventoSignificativo(
        int    $codigoMotivo,
        string $descripcion,
        string $fechaInicio,
        string $fechaFin
    ): string {
        $cuis = $this->codigos->obtenerCuis();
        $cufd = $this->codigos->obtenerCufd()['codigo'];

        $params = [
            'SolicitudEventoSignificativo' => [
                'codigoAmbiente'       => $this->config['ambiente'],
                'codigoMotivoEvento'   => $codigoMotivo,
                'codigoPuntoVenta'     => $this->config['punto_venta'],
                'codigoSistema'        => $this->config['codigo_sistema'],
                'codigoSucursal'       => $this->config['sucursal'],
                'cufd'                 => $cufd,
                'cufdEvento'           => $cufd,
                'cuis'                 => $cuis,
                'descripcion'          => $descripcion,
                'fechaHoraFinEvento'   => $fechaFin,
                'fechaHoraInicioEvento'=> $fechaInicio,
                'nit'                  => $this->config['nit'],
            ],
        ];

        $response  = $this->soap->call('registroEventoSignificativo', $params);
        $resultado = $response['RespuestaRegistroEventoSignificativo'] ?? [];

        if (empty($resultado['transaccion'])) {
            $mensajes = $this->extraerMensajes($resultado);
            throw SiatException::transaccionRechazada("Error al registrar evento significativo: {$mensajes}");
        }

        return $resultado['codigoRecepcionEventoSignificativo'] ?? '';
    }

    /**
     * Cierre de operaciones de un sistema.
     *
     * @return bool
     * @throws SiatException
     */
    public function cierreOperacionesSistema(): bool
    {
        $cuis = $this->codigos->obtenerCuis();

        $params = [
            'SolicitudOperaciones' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoPuntoVenta' => $this->config['punto_venta'] ?? 0,
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'cuis'             => $cuis,
                'nit'              => $this->config['nit'],
            ],
        ];

        $response  = $this->soap->call('cierreOperacionesSistema', $params);
        $resultado = $response['RespuestaCierreSistemas'] ?? [];

        if (empty($resultado['transaccion'])) {
            $mensajes = $this->extraerMensajes($resultado);
            throw SiatException::transaccionRechazada("Error al cerrar operaciones del sistema: {$mensajes}");
        }

        return true;
    }

    /**
     * Helper para extraer y concatenar los mensajes de error devueltos por el SIAT.
     */
    protected function extraerMensajes(?array $resultado): string
    {
        if (empty($resultado['mensajesList'])) return '';
        $msgs = $resultado['mensajesList'];
        if (isset($msgs['MensajeServicio'])) {
            $msgs = is_array($msgs['MensajeServicio'][0] ?? null)
                ? $msgs['MensajeServicio']
                : [$msgs['MensajeServicio']];
        }
        return implode(' | ', array_map(
            fn($m) => "[{$m['codigo']}] {$m['descripcion']}",
            (array) $msgs
        ));
    }
}

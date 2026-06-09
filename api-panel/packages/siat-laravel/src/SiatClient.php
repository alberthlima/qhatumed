<?php

namespace Misalud\Siat;

use Misalud\Siat\DTOs\FacturaCabeceraDTO;
use Misalud\Siat\DTOs\FacturaDetalleDTO;
use Misalud\Siat\DTOs\FacturaEmitidaDTO;
use Misalud\Siat\Services\CodigosService;
use Misalud\Siat\Services\CompraVentaService;
use Misalud\Siat\Services\SincronizacionService;
use Misalud\Siat\Services\OperacionesService;
use Misalud\Siat\Exceptions\SiatException;

/**
 * SiatClient — Punto de Entrada Principal del SDK
 *
 * Esta es la clase que usan los proyectos que instalan el paquete.
 * Se inyecta vía el Facade Siat:: o directamente vía el container de Laravel.
 *
 * Uso en cualquier proyecto:
 *   $factura = app(SiatClient::class)->emitirCompraVenta($cabecera, $detalles);
 *
 *   // O con el Facade:
 *   $factura = Siat::emitirCompraVenta($cabecera, $detalles);
 */
class SiatClient
{
    protected array $config;
    protected CodigosService $codigos;
    protected CompraVentaService $compraVenta;
    protected SincronizacionService $sincronizacion;
    protected OperacionesService $operaciones;

    public function __construct(array $config)
    {
        $this->validarConfig($config);
        $this->config        = $config;
        $this->codigos       = new CodigosService($config);
        $this->compraVenta   = new CompraVentaService($config, $this->codigos);
        $this->sincronizacion = new SincronizacionService($config);
        $this->operaciones    = new OperacionesService($config, $this->codigos);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API Pública
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Acceso al servicio de sincronización de catálogos.
     *
     * Ejemplo:
     *   Siat::sincronizacion()->obtener('metodos_pago');
     *   Siat::sincronizacion()->sincronizarTodo();
     */
    public function sincronizacion(): SincronizacionService
    {
        return $this->sincronizacion;
    }

    /**
     * Acceso al servicio de operaciones (Puntos de venta, eventos).
     */
    public function operaciones(): OperacionesService
    {
        return $this->operaciones;
    }

    /**
     * Verifica que todos los catálogos obligatorios estén sincronizados.
     * Llama a esto antes de emitir facturas en producción.
     *
     * @throws SiatException si faltan catálogos
     */
    public function validarCatalogos(): void
    {
        $estado = $this->sincronizacion->verificarEstado();

        if (!$estado['listo']) {
            $faltantes = implode(', ', $estado['faltantes']);
            throw new SiatException(
                "Catálogos SIAT no sincronizados: {$faltantes}. "
                . "Ejecute: php artisan siat:sincronizar"
            );
        }
    }

    /**
     * Emite una factura de Compra-Venta (Sector 1).
     * Válida para: Farmacias, Tiendas, Comercios generales.
     *
     * @param FacturaCabeceraDTO   $cabecera  Datos del cliente y totales
     * @param FacturaDetalleDTO[]  $detalles  Ítems de la factura
     * @return FacturaEmitidaDTO              CUF, QR URL, estado
     */
    public function emitirCompraVenta(FacturaCabeceraDTO $cabecera, array $detalles): FacturaEmitidaDTO
    {
        return $this->compraVenta->emitir($cabecera, $detalles);
    }

    /**
     * Anula una factura emitida.
     *
     * @param string $cuf          CUF de la factura a anular
     * @param int    $codigoMotivo 1=Error en factura, 2=Devolución, etc.
     */
    public function anularFactura(string $cuf, int $codigoMotivo = 1): bool
    {
        return $this->compraVenta->anular($cuf, $codigoMotivo);
    }

    /**
     * Verifica la conectividad con el SIAT.
     */
    public function verificarConexion(): bool
    {
        return $this->codigos->verificarComunicacion();
    }

    /**
     * Verifica si un NIT está activo en el SIN.
     */
    public function verificarNit(int $nit): bool
    {
        return $this->codigos->verificarNit($nit);
    }

    /**
     * Obtiene el CUIS actual (desde caché o SIAT si venció).
     */
    public function obtenerCuis(): string
    {
        return $this->codigos->obtenerCuis();
    }

    /**
     * Obtiene el CUFD del día (desde caché o SIAT si venció).
     */
    public function obtenerCufd(): array
    {
        return $this->codigos->obtenerCufd();
    }

    /**
     * Fuerza renovación del CUFD (útil al inicio de cada jornada).
     */
    public function renovarCufd(): array
    {
        return $this->codigos->renovarCufd();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Configuración
    // ─────────────────────────────────────────────────────────────────────────

    public function getConfig(): array
    {
        return $this->config;
    }

    public function esPiloto(): bool
    {
        return $this->config['ambiente'] === 2;
    }

    public function esComputarizada(): bool
    {
        return $this->config['modalidad'] === 2;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validación de configuración al arrancar
    // ─────────────────────────────────────────────────────────────────────────

    protected function validarConfig(array $config): void
    {
        $requeridos = ['nit', 'token', 'codigo_sistema', 'url'];

        foreach ($requeridos as $campo) {
            if (empty($config[$campo])) {
                throw SiatException::configInvalida($campo);
            }
        }

        if ($config['modalidad'] === 1 && empty($config['certificado']['ruta'])) {
            throw SiatException::configInvalida(
                'certificado.ruta (requerido para modalidad Electrónica)'
            );
        }
    }
}

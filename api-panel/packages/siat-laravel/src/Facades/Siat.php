<?php

namespace Misalud\Siat\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Misalud\Siat\DTOs\FacturaEmitidaDTO emitirCompraVenta(\Misalud\Siat\DTOs\FacturaCabeceraDTO $cabecera, array $detalles)
 * @method static bool anularFactura(string $cuf, int $codigoMotivo = 1)
 * @method static \Misalud\Siat\Services\SincronizacionService sincronizacion()
 * @method static \Misalud\Siat\Services\OperacionesService operaciones()
 * @method static void validarCatalogos()
 * @method static bool verificarConexion()
 * @method static bool verificarNit(int $nit)
 * @method static string obtenerCuis()
 * @method static array obtenerCufd()
 * @method static array renovarCufd()
 * @method static bool esPiloto()
 * @method static bool esComputarizada()
 *
 * @see \Misalud\Siat\SiatClient
 */
class Siat extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'siat';
    }
}

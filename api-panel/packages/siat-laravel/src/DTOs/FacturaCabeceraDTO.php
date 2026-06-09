<?php

namespace Misalud\Siat\DTOs;

/**
 * FacturaCabeceraDTO
 * Datos del encabezado de la factura (cliente + totales).
 */
class FacturaCabeceraDTO
{
    public ?string $cuf  = null;
    public ?string $cufd = null;

    public function __construct(
        public readonly int       $numeroFactura,
        public readonly \DateTime $fechaEmision,
        public readonly string    $razonSocialEmisor,
        public readonly string    $municipio,
        public readonly string    $direccion,
        public readonly float     $montoTotal,
        public readonly int       $codigoMetodoPago,       // 1=Efectivo, 2=Tarjeta, 3=Transf...
        public readonly ?string   $clienteNombre     = null,
        public readonly ?string   $clienteDocumento  = null,
        public readonly ?int      $codigoTipoDocumento = 5, // 5=Sin Nombre/Sin Doc
        public readonly ?string   $codigoCliente     = null,
        public readonly ?float    $montoTotalSujetoIva = null,
        public readonly ?int      $codigoMoneda      = 1,   // 1 = BOB
        public readonly ?float    $tipoCambio        = 1,
        public readonly ?float    $descuento         = 0,
        public readonly ?string   $leyenda           = null,
        public readonly ?string   $telefono          = null,
    ) {}

    /**
     * Crea una cabecera para una venta anónima (sin CI del cliente).
     */
    public static function anonima(
        int    $numeroFactura,
        string $razonSocialEmisor,
        string $municipio,
        string $direccion,
        float  $montoTotal,
        int    $codigoMetodoPago = 1,
    ): self {
        return new self(
            numeroFactura:       $numeroFactura,
            fechaEmision:        new \DateTime(),
            razonSocialEmisor:   $razonSocialEmisor,
            municipio:           $municipio,
            direccion:           $direccion,
            montoTotal:          $montoTotal,
            codigoMetodoPago:    $codigoMetodoPago,
            clienteNombre:       'SIN NOMBRE',
            clienteDocumento:    '0',
            codigoTipoDocumento: 5,  // 5 = Sin Documento
            codigoCliente:       '0',
        );
    }
}

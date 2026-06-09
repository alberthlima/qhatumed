<?php

namespace Misalud\Siat\DTOs;

/**
 * FacturaDetalleDTO
 * Representa una línea de detalle en la factura.
 */
class FacturaDetalleDTO
{
    public readonly float $subTotal;

    public function __construct(
        public readonly string $descripcion,
        public readonly float  $cantidad,
        public readonly float  $precioUnitario,
        public readonly string $actividadEconomica,  // Código NANDINA / actividad económica SIAT
        public readonly int    $codigoProductoSin,   // Código del catálogo SIN
        public readonly string $codigoProducto,      // Código interno del sistema
        public readonly ?int   $unidadMedida  = 1,   // 1 = Unidad
        public readonly ?float $descuento     = 0,
    ) {
        $this->subTotal = ($this->precioUnitario * $this->cantidad) - ($this->descuento ?? 0);
    }

    /**
     * Factory para un ítem de farmacia (medicamento).
     * El código SIN 622539 corresponde a la actividad de venta al por menor de productos farmacéuticos.
     */
    public static function medicamento(
        string $nombre,
        float  $cantidad,
        float  $precio,
        string $codigoInterno,
        float  $descuento = 0,
    ): self {
        return new self(
            descripcion:         $nombre,
            cantidad:            $cantidad,
            precioUnitario:      $precio,
            actividadEconomica:  '477300', // Venta al por menor en farmacias
            codigoProductoSin:   622539,   // Medicamentos y productos farmacéuticos
            codigoProducto:      $codigoInterno,
            unidadMedida:        1,
            descuento:           $descuento,
        );
    }

    /**
     * Factory para un ítem de servicio genérico.
     */
    public static function servicio(
        string $descripcion,
        float  $monto,
        string $codigoInterno,
        string $actividadEconomica = '749000',
        int    $codigoProductoSin  = 88131,
    ): self {
        return new self(
            descripcion:         $descripcion,
            cantidad:            1,
            precioUnitario:      $monto,
            actividadEconomica:  $actividadEconomica,
            codigoProductoSin:   $codigoProductoSin,
            codigoProducto:      $codigoInterno,
            unidadMedida:        1,
        );
    }
}

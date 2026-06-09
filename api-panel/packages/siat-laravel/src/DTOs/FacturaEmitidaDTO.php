<?php

namespace Misalud\Siat\DTOs;

/**
 * FacturaEmitidaDTO
 * Resultado de una emisión exitosa. Lo que guardas en tu BD.
 */
readonly class FacturaEmitidaDTO
{
    public function __construct(
        public string    $cuf,
        public string    $cufd,
        public string    $codigoRecepcion,
        public int       $numeroFactura,
        public \DateTime $fechaEmision,
        public string    $estado,       // VALIDA | OBSERVADA | ANULADA
        public string    $qrUrl,
    ) {}

    public function toArray(): array
    {
        return [
            'cuf'               => $this->cuf,
            'cufd'              => $this->cufd,
            'codigo_recepcion'  => $this->codigoRecepcion,
            'numero_factura'    => $this->numeroFactura,
            'fecha_emision'     => $this->fechaEmision->format('Y-m-d H:i:s'),
            'estado'            => $this->estado,
            'qr_url'            => $this->qrUrl,
        ];
    }
}

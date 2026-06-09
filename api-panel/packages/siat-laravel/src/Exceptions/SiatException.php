<?php

namespace Misalud\Siat\Exceptions;

use RuntimeException;

class SiatException extends RuntimeException
{
    /**
     * Código de error devuelto por el SIAT.
     */
    protected int $siatCode;

    /**
     * Indica si el error es reintentable (timeouts, problemas del servidor SIAT).
     */
    protected bool $retryable;

    public function __construct(
        string $message,
        int    $siatCode = 0,
        bool   $retryable = false,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $siatCode, $previous);
        $this->siatCode  = $siatCode;
        $this->retryable = $retryable;
    }

    public static function fromSoapFault(\SoapFault $fault): self
    {
        return new self(
            "Error SOAP SIAT: [{$fault->faultcode}] {$fault->faultstring}",
            0,
            true, // Los faults SOAP suelen ser transitorios
            $fault
        );
    }

    public static function transaccionRechazada(string $mensaje, int $codigo = 0): self
    {
        // Códigos de error reintentables del SIAT (timeouts, servidor ocupado)
        $codigosReintentables = [921, 922, 923, 970, 971, 972];

        return new self(
            "SIAT rechazó la transacción: {$mensaje}",
            $codigo,
            in_array($codigo, $codigosReintentables)
        );
    }

    public static function configInvalida(string $campo): self
    {
        return new self("Configuración SIAT inválida o faltante: {$campo}", 0, false);
    }

    public function getSiatCode(): int
    {
        return $this->siatCode;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}

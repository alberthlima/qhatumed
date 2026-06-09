<?php

namespace Misalud\Siat\Utils;

use Misalud\Siat\Exceptions\SiatException;

/**
 * CufGenerator
 *
 * Genera el Código Único de Factura (CUF) localmente, sin llamadas al SIAT.
 *
 * Fórmula oficial del SIN (Anexo Técnico Bolivia):
 * CUF = NIT(13) + FechaHoraMs(23) + CodigoSucursal(4) + CodigoModalidad(2)
 *       + CodigoEmision(1) + CodigoSector(2) + NumeroFactura(10)
 *       + CodigoPuntoVenta(4) + TipoFacturaDocumento(2)
 *       + ParteCodigoControl(6) + DigitoVerificador(1)
 *
 * El resultado se codifica en Base16 (hex).
 */
class CufGenerator
{
    /**
     * Genera el CUF para una factura.
     *
     * @param int    $nit                NIT del emisor
     * @param \DateTime $fechaEmision    Fecha y hora de emisión
     * @param int    $codigoSucursal     Código de sucursal (0 = Casa Matriz)
     * @param int    $codigoModalidad    1=Electrónica, 2=Computarizada
     * @param int    $codigoEmision      1=Online, 2=Offline, 3=Masiva
     * @param int    $codigoDocSector    Sector (1=CompraVenta, 2=Alquiler, etc.)
     * @param int    $numeroFactura      Número correlativo de la factura
     * @param int    $codigoPuntoVenta   Código de Punto de Venta (0 = sin PV)
     * @param int    $tipoFactura        1=Factura, 2=Nota, etc.
     * @param string $codigoControl      Los primeros 6 chars del CodigoControl del CUFD
     * @return string                    CUF generado (Base16 en mayúsculas)
     * @throws SiatException
     */
    public function generar(
        int       $nit,
        \DateTime $fechaEmision,
        int       $codigoSucursal,
        int       $codigoModalidad,
        int       $codigoEmision,
        int       $codigoDocSector,
        int       $numeroFactura,
        int       $codigoPuntoVenta,
        int       $tipoFactura,
        string    $codigoControl
    ): string {
        // Validaciones previas
        if (strlen($codigoControl) < 6) {
            throw new SiatException('El código de control del CUFD debe tener al menos 6 caracteres.');
        }

        // Construye la cadena según el Anexo Técnico del SIN
        $cadena = $this->construirCadenaBase(
            $nit, $fechaEmision, $codigoSucursal,
            $codigoModalidad, $codigoEmision, $codigoDocSector,
            $numeroFactura, $codigoPuntoVenta, $tipoFactura, $codigoControl
        );

        // Calcula el dígito verificador Módulo 11
        $digitoVerificador = $this->calcularDigitoMod11($cadena);

        // El CUF final es la cadena + dígito verificador, en hex (Base16) mayúsculas
        $cufNumerico = $cadena . $digitoVerificador;

        return strtoupper(base_convert($cufNumerico, 10, 16));
    }

    /**
     * Construye la cadena numérica base del CUF según el Anexo Técnico.
     */
    protected function construirCadenaBase(
        int       $nit,
        \DateTime $fechaEmision,
        int       $codigoSucursal,
        int       $codigoModalidad,
        int       $codigoEmision,
        int       $codigoDocSector,
        int       $numeroFactura,
        int       $codigoPuntoVenta,
        int       $tipoFactura,
        string    $codigoControl
    ): string {
        // NIT: 13 dígitos con ceros a la izquierda
        $nitStr = str_pad((string) $nit, 13, '0', STR_PAD_LEFT);

        // Fecha/Hora: yyyyMMddHHmmsssss (23 chars)
        // Los últimos 3 son milisegundos (PHP usa microsegundos, tomamos primeros 3 dígitos)
        $fechaStr = $fechaEmision->format('YmdHis');
        $ms       = substr((string) $fechaEmision->format('u'), 0, 3);
        $fechaTs  = $fechaStr . str_pad($ms, 3, '0', STR_PAD_LEFT); // 17 chars

        // Sucursal: 4 dígitos
        $sucursalStr = str_pad((string) $codigoSucursal, 4, '0', STR_PAD_LEFT);

        // Modalidad: 2 dígitos
        $modalidadStr = str_pad((string) $codigoModalidad, 2, '0', STR_PAD_LEFT);

        // Tipo de emisión: 1 dígito
        $emisionStr = (string) $codigoEmision;

        // Sector del documento: 2 dígitos
        $sectorStr = str_pad((string) $codigoDocSector, 2, '0', STR_PAD_LEFT);

        // Número de factura: 10 dígitos
        $nroFactStr = str_pad((string) $numeroFactura, 10, '0', STR_PAD_LEFT);

        // Punto de venta: 4 dígitos
        $pvStr = str_pad((string) $codigoPuntoVenta, 4, '0', STR_PAD_LEFT);

        // Tipo de factura: 2 dígitos
        $tipoStr = str_pad((string) $tipoFactura, 2, '0', STR_PAD_LEFT);

        // Parte del código de control: primeros 6 chars del CodigoControl del CUFD
        $ctrlStr = substr($codigoControl, 0, 6);

        // Concatenación en el orden exacto del Anexo Técnico
        return $nitStr . $fechaTs . $sucursalStr . $modalidadStr
            . $emisionStr . $sectorStr . $nroFactStr
            . $pvStr . $tipoStr . $ctrlStr;
    }

    /**
     * Calcula el dígito verificador usando el algoritmo Módulo 11 del SIN.
     *
     * Se toman los caracteres de la cadena, se asigna un peso creciente
     * (2, 3, 4, ... 9, 2, 3, ...) y se calcula: 11 - (suma % 11).
     * Si el resultado es 10 u 11, se usa 1.
     */
    protected function calcularDigitoMod11(string $cadena): int
    {
        $suma  = 0;
        $peso  = 2;
        $largo = strlen($cadena);

        // Se recorre de derecha a izquierda
        for ($i = $largo - 1; $i >= 0; $i--) {
            $digito = (int) $cadena[$i];
            $suma  += $digito * $peso;
            $peso   = ($peso === 9) ? 2 : $peso + 1;
        }

        $residuo   = $suma % 11;
        $verificador = 11 - $residuo;

        // Si el verificador es 10 u 11, se convierte a 1 (regla del SIN)
        return ($verificador >= 10) ? 1 : $verificador;
    }
}

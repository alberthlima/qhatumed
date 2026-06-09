<?php

namespace App\Services;

use App\Models\Sale;
use App\Helpers\NumberTranslateHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

/**
 * SiatPdfService
 * Genera el documento PDF de la factura cumpliendo con la normativa del SIN (Bolivia).
 */
class SiatPdfService
{
    /**
     * Genera el PDF de una factura en formato A4 estándar.
     * 
     * @param Sale $sale
     * @return string Contenido binario del PDF
     */
    public function generateInvoice(Sale $sale)
    {
        $sale->load(['items.product', 'customer', 'user']);

        // 1. Datos para el QR (Formato estándar Bolivia)
        $config = $this->getSiatConfig();
        $qrContent = sprintf(
            "%s|%s|%s|%s|%.2f|%.2f|%s|%s|0|0|0|0",
            $config['nit'],
            $sale->cuf,
            $sale->invoice_number,
            $sale->sale_date->format('d/m/Y'),
            $sale->total_amount,
            $sale->total_amount, // Asumimos todo sujeto a IVA
            '', // Código de control (vacío para facturación en línea v2)
            $sale->nit_ci
        );

        // Generar QR en base64 para embeber en el PDF
        $qrBase64 = base64_encode(QrCode::format('svg')->size(150)->generate($qrContent));
        $qrImage = 'data:image/svg+xml;base64,' . $qrBase64;

        // 2. Monto en letras
        $literalAmount = NumberTranslateHelper::toLiteral($sale->total_amount);

        // 3. Renderizar PDF
        $pdf = Pdf::loadView('pdf.invoice', [
            'sale'          => $sale,
            'qrImage'       => $qrImage,
            'literalAmount' => $literalAmount,
            'config'        => $config
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /**
     * Genera el PDF de una factura en formato de ticket térmico (80mm).
     * 
     * @param Sale $sale
     * @return string Contenido binario del PDF
     */
    public function generateThermal(Sale $sale)
    {
        $sale->load(['items.product', 'customer', 'user']);

        // 1. Datos para el QR
        $config = $this->getSiatConfig();
        $qrContent = sprintf(
            "%s|%s|%s|%s|%.2f|%.2f|%s|%s|0|0|0|0",
            $config['nit'],
            $sale->cuf,
            $sale->invoice_number,
            $sale->sale_date->format('d/m/Y'),
            $sale->total_amount,
            $sale->total_amount,
            '',
            $sale->nit_ci
        );

        $qrCode = base64_encode(QrCode::format('svg')->size(120)->generate($qrContent));

        // 2. Renderizar PDF (ancho de 80mm = 226.77 pt)
        $pdf = Pdf::loadView('siat.factura_termica', [
            'sale'   => $sale,
            'qrCode' => $qrCode,
            'config' => $config
        ]);

        // Ajustar altura a un valor razonable para un ticket largo
        $pdf->setPaper([0, 0, 226.77, 600], 'portrait');

        return $pdf->output();
    }

    /**
     * Genera el PDF de una factura en formato tipo carta (A4).
     * 
     * @param Sale $sale
     * @return string Contenido binario del PDF
     */
    public function generateLetter(Sale $sale)
    {
        $sale->load(['items.product', 'customer', 'user']);

        // 1. Datos para el QR
        $config = $this->getSiatConfig();
        $qrContent = sprintf(
            "%s|%s|%s|%s|%.2f|%.2f|%s|%s|0|0|0|0",
            $config['nit'],
            $sale->cuf,
            $sale->invoice_number,
            $sale->sale_date->format('d/m/Y'),
            $sale->total_amount,
            $sale->total_amount,
            '',
            $sale->nit_ci
        );

        $qrCode = base64_encode(QrCode::format('svg')->size(120)->generate($qrContent));

        // 2. Renderizar PDF
        $pdf = Pdf::loadView('siat.factura_carta', [
            'sale'   => $sale,
            'qrCode' => $qrCode,
            'config' => $config
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /**
     * Obtiene y unifica la configuración del SIAT con la configuración persistida en BD.
     */
    private function getSiatConfig(): array
    {
        $config = config('siat') ?? [];

        try {
            $setting = \App\Models\CompanySetting::first();
            if ($setting) {
                if ($setting->legal_name) $config['razon_social'] = $setting->legal_name;
                if ($setting->nit) $config['nit'] = $setting->nit;
                if ($setting->siat_branch_code !== null) $config['sucursal'] = $setting->siat_branch_code;
                if ($setting->siat_pos_code !== null) $config['punto_venta'] = $setting->siat_pos_code;
            }
        } catch (\Exception $e) {
            // Ignorar errores si no está migrado
        }

        // Valores por defecto
        if (empty($config['razon_social'])) $config['razon_social'] = 'FARMACIA MISALUD';
        if (empty($config['nit'])) $config['nit'] = '6789406016';
        if (empty($config['direccion'])) $config['direccion'] = 'Av. Principal #123';
        if (empty($config['municipio'])) $config['municipio'] = 'Santa Cruz';
        if (empty($config['leyenda'])) {
            $config['leyenda'] = 'Ley N° 453: El proveedor de servicios debe prioritariamente informar sobre las características de los productos.';
        }

        return $config;
    }

    /**
     * Genera el PDF de un recibo térmico de venta (80mm) sin datos del SIAT.
     * 
     * @param Sale $sale
     * @return string Contenido binario del PDF
     */
    public function generateReceipt(Sale $sale)
    {
        $sale->load(['items.product', 'customer', 'user']);

        $pdf = Pdf::loadView('siat.recibo_termico', [
            'sale' => $sale
        ]);

        $pdf->setPaper([0, 0, 226.77, 600], 'portrait');

        return $pdf->output();
    }
}

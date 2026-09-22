<?php

namespace App\Services;

use App\Models\Sale;
use App\Helpers\NumberTranslateHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;

/**
 * SiatPdfService
 * Genera el documento PDF de la factura cumpliendo con la normativa del SIN (Bolivia).
 */
class SiatPdfService
{
    /**
     * Genera el PDF de una factura en formato A4 estándar.
     */
    public function generateInvoice(Sale $sale)
    {
        return $this->generateLetter($sale);
    }

    /**
     * Genera el PDF de una factura en formato de ticket térmico (80mm).
     */
    public function generateThermal(Sale $sale)
    {
        $sale->loadMissing(['items.product', 'customer', 'user']);

        $config = $this->getSiatConfig();
        $dateFormatted = Carbon::parse($sale->sale_date)->format('d/m/Y H:i');

        // URL Oficial de Consulta QR del SIN para Facturas en Línea
        $qrContent = sprintf(
            "https://pilotosiat.impuestos.gob.bo/consulta/QR?nit=%s&cuf=%s&numero=%s&t=1",
            $config['nit'],
            $sale->cuf,
            $sale->invoice_number
        );

        // Generar QR SVG en base64
        $qrCode = base64_encode(QrCode::format('svg')->size(120)->generate($qrContent));
        $literalAmount = NumberTranslateHelper::toLiteral((float)$sale->total_amount);

        $pdf = Pdf::loadView('siat.factura_termica', [
            'sale'          => $sale,
            'qrCode'        => $qrCode,
            'qrUrl'         => $qrContent,
            'dateFormatted' => $dateFormatted,
            'literalAmount' => $literalAmount,
            'config'        => $config
        ]);

        // 80mm de ancho (226.77 pt) con alto dinámico
        $itemCount = count($sale->items);
        $height = max(550, 380 + ($itemCount * 30));
        $pdf->setPaper([0, 0, 226.77, $height], 'portrait');

        return $pdf->output();
    }

    /**
     * Genera el PDF de una factura en formato tipo carta (A4).
     */
    public function generateLetter(Sale $sale)
    {
        $sale->loadMissing(['items.product', 'customer', 'user']);

        $config = $this->getSiatConfig();
        $dateFormatted = Carbon::parse($sale->sale_date)->format('d/m/Y H:i');

        $qrContent = sprintf(
            "https://pilotosiat.impuestos.gob.bo/consulta/QR?nit=%s&cuf=%s&numero=%s&t=1",
            $config['nit'],
            $sale->cuf,
            $sale->invoice_number
        );

        $qrCode = base64_encode(QrCode::format('svg')->size(130)->generate($qrContent));
        $literalAmount = NumberTranslateHelper::toLiteral((float)$sale->total_amount);

        $pdf = Pdf::loadView('siat.factura_carta', [
            'sale'          => $sale,
            'qrCode'        => $qrCode,
            'qrUrl'         => $qrContent,
            'dateFormatted' => $dateFormatted,
            'literalAmount' => $literalAmount,
            'config'        => $config
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /**
     * Genera el PDF de un recibo térmico de venta (80mm) sin datos del SIAT.
     */
    public function generateReceipt(Sale $sale)
    {
        $sale->loadMissing(['items.product', 'customer', 'user']);
        $dateFormatted = Carbon::parse($sale->sale_date)->format('d/m/Y H:i');

        $pdf = Pdf::loadView('siat.recibo_termico', [
            'sale'          => $sale,
            'dateFormatted' => $dateFormatted
        ]);

        $itemCount = count($sale->items);
        $height = max(450, 300 + ($itemCount * 30));
        $pdf->setPaper([0, 0, 226.77, $height], 'portrait');

        return $pdf->output();
    }

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
        } catch (\Throwable $e) {}

        if (empty($config['razon_social'])) $config['razon_social'] = 'FARMACIA MISALUD';
        if (empty($config['nit'])) $config['nit'] = '6789406016';
        if (empty($config['direccion'])) $config['direccion'] = 'Av. Principal #123';
        if (empty($config['municipio'])) $config['municipio'] = 'Santa Cruz';
        if (empty($config['leyenda'])) {
            $config['leyenda'] = 'Ley N° 453: El proveedor de servicios debe prioritariamente informar sobre las características de los productos.';
        }

        return $config;
    }
}
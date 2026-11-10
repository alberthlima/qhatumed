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
        $dateFormatted = $this->resolveSaleDateTime($sale);

        $ambiente = $config['ambiente'] ?? 2;
        $domain = ($ambiente == 1) ? 'siat.impuestos.gob.bo' : 'pilotosiat.impuestos.gob.bo';

        // URL Oficial de Consulta QR del SIN para Facturas en Línea
        $qrContent = sprintf(
            "https://%s/consulta/QR?nit=%s&cuf=%s&numero=%s&t=1",
            $domain,
            $config['nit'],
            $sale->cuf,
            $sale->invoice_number
        );

        // Generar QR SVG en base64 (Vectorial y nítido para impresoras térmicas)
        $qrCode = base64_encode(QrCode::format('svg')->size(120)->generate($qrContent));
        $giftCardAmt = round((float) ($sale->gift_card_amount ?? 0), 2);
        $payableAmount = round(max(0.0, (float) $sale->total_amount - $giftCardAmt), 2);
        $literalAmount = NumberTranslateHelper::toLiteral($payableAmount);

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
        $height = max(580, 400 + ($itemCount * 30));
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
        $dateFormatted = $this->resolveSaleDateTime($sale);

        $ambiente = $config['ambiente'] ?? 2;
        $domain = ($ambiente == 1) ? 'siat.impuestos.gob.bo' : 'pilotosiat.impuestos.gob.bo';

        $qrContent = sprintf(
            "https://%s/consulta/QR?nit=%s&cuf=%s&numero=%s&t=1",
            $domain,
            $config['nit'],
            $sale->cuf,
            $sale->invoice_number
        );

        $qrCode = base64_encode(QrCode::format('svg')->size(130)->generate($qrContent));
        $giftCardAmt = round((float) ($sale->gift_card_amount ?? 0), 2);
        $payableAmount = round(max(0.0, (float) $sale->total_amount - $giftCardAmt), 2);
        $literalAmount = NumberTranslateHelper::toLiteral($payableAmount);

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
        $dateFormatted = $this->resolveSaleDateTime($sale);

        $pdf = Pdf::loadView('siat.recibo_termico', [
            'sale'          => $sale,
            'dateFormatted' => $dateFormatted
        ]);

        $itemCount = count($sale->items);
        $height = max(450, 300 + ($itemCount * 30));
        $pdf->setPaper([0, 0, 226.77, $height], 'portrait');

        return $pdf->output();
    }

    /**
     * Resuelve la fecha y hora exacta de emisión asegurando que nunca salga 00:00.
     */
    private function resolveSaleDateTime(Sale $sale): string
    {
        // El servidor almacena timestamps en UTC; convertimos a la hora oficial de Bolivia (America/La_Paz)
        $saleCarbon = Carbon::parse($sale->sale_date)->timezone('America/La_Paz');
        
        // Si la hora es 00:00 pero existe created_at con hora válida, usar created_at
        if ($saleCarbon->format('H:i') === '00:00') {
            if ($sale->created_at) {
                $saleCarbon = Carbon::parse($sale->created_at)->timezone('America/La_Paz');
            } else {
                $saleCarbon = Carbon::now('America/La_Paz');
            }
        }

        return $saleCarbon->format('d/m/Y H:i');
    }

    private function getSiatConfig(): array
    {
        $config = config('siat') ?? [];

        try {
            $setting = \App\Models\CompanySetting::first();
            if ($setting) {
                if ($setting->legal_name) $config['razon_social'] = $setting->legal_name;
                if ($setting->nit) $config['nit'] = $setting->nit;
                if ($setting->siat_branch_code !== null) $config['sucursal'] = (int) $setting->siat_branch_code;
                if ($setting->siat_pos_code !== null) $config['punto_venta'] = (int) $setting->siat_pos_code;
                if ($setting->siat_environment !== null) $config['ambiente'] = (int) $setting->siat_environment;
                if ($setting->siat_modality !== null) $config['modalidad'] = (int) $setting->siat_modality;
                if (!empty($setting->address)) $config['direccion'] = $setting->address;
                if (!empty($setting->city)) $config['municipio'] = $setting->city;
                if (!empty($setting->phone)) $config['telefono'] = $setting->phone;
            }
        } catch (\Throwable $e) {}

        if (empty($config['razon_social'])) $config['razon_social'] = 'LIMA ALBERTH MAYRON';
        if (empty($config['nit'])) $config['nit'] = '6789406016';
        if (empty($config['direccion'])) $config['direccion'] = 'Av. Principal 123';
        if (empty($config['municipio'])) $config['municipio'] = 'La Paz';
        if (empty($config['telefono'])) $config['telefono'] = '2222222';
        if (!isset($config['sucursal'])) $config['sucursal'] = 0;
        if (!isset($config['punto_venta'])) $config['punto_venta'] = 0;
        if (!isset($config['ambiente'])) $config['ambiente'] = 2;
        if (!isset($config['modalidad'])) $config['modalidad'] = 1;
        if (empty($config['leyenda'])) {
            $config['leyenda'] = 'Ley N° 453: El proveedor de servicios debe prioritariamente informar sobre las características de los productos.';
        }

        return $config;
    }
}
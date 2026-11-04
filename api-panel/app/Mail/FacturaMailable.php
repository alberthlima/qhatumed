<?php

namespace App\Mail;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacturaMailable extends Mailable
{
    use Queueable, SerializesModels;

    public Sale $sale;
    public string $pdfContent;
    public ?string $xmlContent;
    public string $tipoOperacion;
    public ?string $motivoAnulacion;

    public function __construct(
        Sale $sale,
        string $pdfContent,
        ?string $xmlContent = null,
        string $tipoOperacion = 'EMISION',
        ?string $motivoAnulacion = null
    ) {
        $this->sale = $sale;
        $this->pdfContent = $pdfContent;
        $this->xmlContent = $xmlContent;
        $this->tipoOperacion = strtoupper($tipoOperacion);
        $this->motivoAnulacion = $motivoAnulacion;
    }

    public function build()
    {
        $subjectPrefix = match ($this->tipoOperacion) {
            'ANULACION' => "Notificación de ANULACIÓN de Factura N° {$this->sale->invoice_number}",
            'REVERSION' => "Notificación de REVERSIÓN DE ANULACIÓN — Factura N° {$this->sale->invoice_number} (VÁLIDA)",
            default     => "Factura Oficial N° {$this->sale->invoice_number}",
        };

        $email = $this->subject("{$subjectPrefix} — {$this->sale->business_name}")
            ->view('emails.factura', [
                'sale'            => $this->sale,
                'tipoOperacion'   => $this->tipoOperacion,
                'motivoAnulacion' => $this->motivoAnulacion,
            ])
            ->attachData($this->pdfContent, "Factura_{$this->sale->invoice_number}.pdf", [
                'mime' => 'application/pdf',
            ]);

        if ($this->xmlContent) {
            $email->attachData($this->xmlContent, "Factura_{$this->sale->invoice_number}.xml", [
                'mime' => 'application/xml',
            ]);
        }

        return $email;
    }
}

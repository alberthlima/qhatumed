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

    public function __construct(Sale $sale, string $pdfContent, ?string $xmlContent = null)
    {
        $this->sale = $sale;
        $this->pdfContent = $pdfContent;
        $this->xmlContent = $xmlContent;
    }

    public function build()
    {
        $email = $this->subject("Factura N° {$this->sale->invoice_number} — {$this->sale->business_name}")
            ->view('emails.factura', [
                'sale' => $this->sale
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
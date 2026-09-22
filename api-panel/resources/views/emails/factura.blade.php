<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 20px; color: #333; }
        .card { max-width: 600px; margin: auto; background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .header { text-align: center; border-bottom: 2px solid #0284c7; padding-bottom: 15px; margin-bottom: 20px; }
        .header h2 { color: #0f172a; margin: 0 0 5px 0; }
        .info-box { background: #f8fafc; border-left: 4px solid #0284c7; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; }
        .footer { text-align: center; font-size: 11px; color: #64748b; margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h2>FARMACIA MISALUD</h2>
            <p style="margin: 0; color: #64748b; font-size: 13px;">Comprobante de Facturación Electrónica en Línea</p>
        </div>

        <p>Estimado(a) <strong>{{ $sale->business_name }}</strong>,</p>
        <p>Agradecemos su compra. Adjunto a este correo encontrará su <strong>Factura Oficial en Formato PDF</strong> y su <strong>Archivo XML Firmado Digitalmente</strong>, válidos ante el Servicio de Impuestos Nacionales (SIN).</p>

        <div class="info-box">
            <strong>N° Factura:</strong> {{ $sale->invoice_number }}<br>
            <strong>NIT/CI:</strong> {{ $sale->nit_ci }}<br>
            <strong>Monto Total:</strong> Bs. {{ number_format($sale->total_amount, 2) }}<br>
            <strong>Código de Autorización (CUF):</strong><br>
            <span style="font-family: monospace; font-size: 11px; word-break: break-all;">{{ $sale->cuf }}</span>
        </div>

        <p style="font-size: 13px;">También puede consultar el estado oficial de su documento fiscal en el portal del SIN escaneando el código QR presente en el PDF adjunto.</p>

        <div class="footer">
            "ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO DE ÉSTA SERÁ SANCIONADO DE ACUERDO A LEY"<br>
            Ley N° 453: El proveedor de servicios debe prioritariamente informar sobre las características de los productos.<br><br>
            © {{ date('Y') }} Farmacia Misalud • Bolivia
        </div>
    </div>
</body>
</html>
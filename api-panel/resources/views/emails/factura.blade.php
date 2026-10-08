<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura Oficial de Compra</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 24px; color: #1e293b; }
        .container { max-width: 620px; margin: 0 auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; padding: 30px 24px; text-align: center; }
        .header h1 { margin: 0 0 6px 0; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
        .header .subtitle { margin: 0; font-size: 13px; color: #e0f2fe; }
        .content { padding: 28px 24px; }
        .greeting { font-size: 15px; margin-bottom: 16px; color: #0f172a; }
        .message { font-size: 14px; line-height: 1.6; color: #475569; margin-bottom: 22px; }
        .details-card { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 24px; }
        .details-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .details-table td { padding: 7px 4px; vertical-align: top; }
        .details-table td.label { font-weight: 600; color: #64748b; width: 42%; }
        .details-table td.value { color: #0f172a; font-weight: 500; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .badge-online { background-color: #dcfce7; color: #15803d; }
        .badge-offline { background-color: #fee2e2; color: #b91c1c; }
        .badge-sector { background-color: #e0f2fe; color: #0369a1; }
        .cuf-box { font-family: 'Courier New', Courier, monospace; font-size: 10px; word-break: break-all; background: #ffffff; padding: 8px 10px; border-radius: 6px; border: 1px dashed #cbd5e1; color: #334155; margin-top: 4px; }
        .attachments-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 16px; margin-bottom: 24px; }
        .attachments-title { font-weight: 700; font-size: 13px; color: #166534; margin-bottom: 8px; display: flex; align-items: center; }
        .attachments-list { margin: 0; padding-left: 20px; font-size: 12.5px; color: #15803d; }
        .attachments-list li { margin-bottom: 4px; }
        .footer { background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 24px; text-align: center; font-size: 11px; color: #94a3b8; line-height: 1.5; }
        .leyenda-legal { font-weight: 600; color: #64748b; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>FARMACIA MISALUD</h1>
            <p class="subtitle">Documento Fiscal Digital Oficial • Servicio de Impuestos Nacionales (SIN)</p>
        </div>

        <div class="content">
            <div class="greeting">
                Estimado(a) <strong>{{ $sale->business_name }}</strong>,
            </div>
            
            <p class="message">
                Le agradecemos por su preferencia. Adjuntamos a este correo los comprobantes digitales oficiales correspondientes a su compra, emitidos conforme a la normativa tributaria boliviana vigente (RND 102100000011).
            </p>

            <div class="details-card">
                <table class="details-table">
                    <tr>
                        <td class="label">N° de Factura:</td>
                        <td class="value"><strong>{{ $sale->invoice_number }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">Fecha y Hora:</td>
                        <td class="value">{{ \Carbon\Carbon::parse($sale->sale_date ?? now())->timezone('America/La_Paz')->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Modalidad de Facturación:</td>
                        <td class="value">
                            @if(config('siat.codigo_modalidad', 1) == 1)
                                <span class="badge badge-sector">Electrónica en Línea (con Firma Digital XMLDSig)</span>
                            @else
                                <span class="badge badge-sector">Computarizada en Línea</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Documento Sector:</td>
                        <td class="value">
                            <span class="badge badge-sector">Documento Sector 1: Factura Compra Venta</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Tipo de Emisión:</td>
                        <td class="value">
                            @if(($sale->codigo_emision ?? 1) == 2)
                                <span class="badge badge-offline">Emisión Fuera de Línea (Contingencia CAFC)</span>
                            @else
                                <span class="badge badge-online">Emisión En Línea (Validada por SIN)</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="label">NIT / CI del Cliente:</td>
                        <td class="value">{{ $sale->nit_ci }}{{ $sale->customer?->complement ? '-' . $sale->customer->complement : '' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Monto Total Sujeto a IVA:</td>
                        <td class="value" style="font-size: 15px; color: #0284c7;"><strong>Bs. {{ number_format($sale->total_amount, 2) }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label" colspan="2" style="padding-top: 10px;">
                            <strong>Código de Autorización (CUF):</strong>
                            <div class="cuf-box">{{ $sale->cuf }}</div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="attachments-card">
                <div class="attachments-title">Archivos Oficiales Adjuntos a este Correo:</div>
                <ul class="attachments-list">
                    <li><strong>Expresión Gráfica (PDF):</strong> Factura Oficial en formato estándar con código QR de verificación fiscal directa en los servidores del SIN.</li>
                    <li><strong>Archivo XML Oficial:</strong> Documento estructurado según esquema oficial XSD del SIN para el sector Compra Venta, con firma digital y hash criptográfico correspondiente.</li>
                </ul>
            </div>

            <p style="font-size: 12px; color: #64748b; line-height: 1.5; margin: 0;">
                Para verificar la validez tributaria de su factura en tiempo real, puede escanear el código QR presente en la expresión gráfica PDF o consultar en el portal oficial del Servicio de Impuestos Nacionales.
            </p>
        </div>

        <div class="footer">
            <div class="leyenda-legal">
                "ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO DE ÉSTA SERÁ SANCIONADO DE ACUERDO A LEY"
            </div>
            <div>
                {{ $sale->leyenda ?? 'Ley N° 453: El proveedor de servicios debe prioritariamente informar sobre las características de los productos.' }}
            </div>
            <div style="margin-top: 12px;">
                © {{ date('Y') }} Farmacia Misalud • Casa Matriz (Sucursal 0, Punto de Venta 0) • Bolivia
            </div>
        </div>
    </div>
</body>
</html>
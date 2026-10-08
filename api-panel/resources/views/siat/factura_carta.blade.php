<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Factura Carta</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; margin: 25px; color: #000; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        
        .header-table { width: 100%; margin-bottom: 15px; }
        .header-left { width: 55%; vertical-align: top; }
        .header-right { width: 45%; vertical-align: top; }
        
        .box {
            border: 1px solid #000;
            padding: 8px 12px;
            border-radius: 4px;
            text-align: left;
            background-color: #fafafa;
        }
        .box h3 { margin: 0 0 5px 0; font-size: 14px; text-align: center; }
        
        .cuf-box {
            font-family: 'Courier', monospace, sans-serif;
            font-size: 8px;
            line-height: 1.15;
            word-break: break-all;
            word-wrap: break-word;
            margin-top: 2px;
        }
        
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-table td { padding: 4px 6px; border: 1px solid #ccc; font-size: 10.5px; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 6px; font-size: 10px; }
        .items-table th { background-color: #f2f2f2; }
        
        .footer-table { width: 100%; margin-top: 10px; }
        .footer-left { width: 75%; vertical-align: top; font-size: 9px; padding-right: 15px; text-align: center; }
        .footer-right { width: 25%; vertical-align: top; text-align: center; }
        
        .qr-code { width: 120px; height: 120px; }
        .italic { font-style: italic; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="header-left">
                <h2 style="margin: 0 0 5px 0; font-size: 16px; text-transform: uppercase;">{{ $config['razon_social'] ?? 'FARMACIA MISALUD' }}</h2>
                <p style="margin: 0; line-height: 1.3; font-size: 10px;">
                    <strong>{{ ($config['sucursal'] ?? 0) == 0 ? 'CASA MATRIZ' : 'SUCURSAL Nº ' . $config['sucursal'] }}</strong><br>
                    No. Punto de Venta: {{ $config['punto_venta'] ?? 0 }}<br>
                    {{ $config['direccion'] ?? 'Av. Principal #123 (Zona Norte)' }}<br>
                    Teléfono: {{ $config['telefono'] ?? '0' }}<br>
                    {{ $config['municipio'] ?? 'Santa Cruz' }} - BOLIVIA
                </p>
            </td>
            <td class="header-right">
                <div class="box">
                    <h3>FACTURA</h3>
                    @if(($sale->codigo_emision ?? 1) == 2)
                        <div style="font-size: 8.5px; font-weight: bold; color: #b71c1c; margin-bottom: 3px;">(MODALIDAD: FUERA DE LÍNEA)</div>
                    @endif
                    @if(!empty($sale->cafc))
                        <strong>N° CAFC:</strong> {{ $sale->cafc }}<br>
                    @endif
                    <strong>NIT:</strong> {{ $config['nit'] ?? '6789406016' }}<br>
                    <strong>N° FACTURA:</strong> {{ $sale->invoice_number }}<br>
                    <strong>CÓD. AUTORIZACIÓN (CUF):</strong><br>
                    <div class="cuf-box">{{ $sale->cuf }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td width="15%" class="bold">FECHA:</td>
            <td width="35%">{{ $dateFormatted }}</td>
            <td width="15%" class="bold">NIT/CI/CEX:</td>
            <td width="35%">{{ $sale->nit_ci }}{{ $sale->customer?->complement ? '-' . $sale->customer->complement : '' }}</td>
        </tr>
        <tr>
            <td class="bold">SEÑOR(ES):</td>
            <td>{{ $sale->business_name }}</td>
            <td class="bold">CÓD. CLIENTE:</td>
            <td>{{ $sale->customer_id ?? $sale->nit_ci ?? '0' }}</td>
        </tr>
        @if($sale->payment_method_code == 10)
        <tr>
            <td class="bold">MEDIO DE PAGO:</td>
            <td colspan="3">COMBINADO (EFECTIVO: Bs. {{ number_format(max(0, $sale->total_amount - ($sale->card_amount ?? 0)), 2) }} / TARJETA: Bs. {{ number_format($sale->card_amount ?? 0, 2) }} - N°: {{ $sale->card_number ?? 'XXXX00000000XXXX' }})</td>
        </tr>
        @elseif($sale->payment_method_code == 2 || !empty($sale->card_number))
        <tr>
            <td class="bold">MEDIO DE PAGO:</td>
            <td colspan="3">TARJETA - N°: {{ $sale->card_number ?? 'XXXX00000000XXXX' }}</td>
        </tr>
        @elseif($sale->payment_method_code == 7)
        <tr>
            <td class="bold">MEDIO DE PAGO:</td>
            <td colspan="3">QR / TRANSFERENCIA BANCARIA</td>
        </tr>
        @elseif($sale->payment_method_code == 27)
        <tr>
            <td class="bold">MEDIO DE PAGO:</td>
            <td colspan="3">GIFT CARD (VALE DE COMPRA){{ $sale->gift_card_code ? ' - N°: ' . $sale->gift_card_code : '' }}</td>
        </tr>
        @elseif($sale->payment_method_code == 35)
        <tr>
            <td class="bold">MEDIO DE PAGO:</td>
            <td colspan="3">PAGO POSTERIOR (VENTA A CRÉDITO)</td>
        </tr>
        @endif
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="8%" class="text-center">CANT.</th>
                <th width="52%">DESCRIPCIÓN</th>
                <th width="12%" class="text-right">P. UNITARIO</th>
                <th width="12%" class="text-right">DESCUENTO</th>
                <th width="16%" class="text-right">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
            <tr>
                <td class="text-center">{{ number_format($item->quantity, 0) }}</td>
                <td>{{ $item->product->name }}</td>
                <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($item->discount ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($item->subtotal ?? ($item->quantity * $item->unit_price - ($item->discount ?? 0)), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            @php
                $grossSubtotal = $sale->items->sum(fn($i) => (float) $i->quantity * (float) $i->unit_price);
                $totalDiscount = (float) $sale->items->sum('discount') + (float) ($sale->additional_discount ?? 0);
            @endphp
            <tr>
                <td colspan="4" class="text-right bold">SUBTOTAL Bs.</td>
                <td class="text-right bold">{{ number_format($grossSubtotal, 2) }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right bold">DESCUENTO Bs.</td>
                <td class="text-right bold">{{ number_format($totalDiscount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right bold">TOTAL Bs.</td>
                <td class="text-right bold">{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right bold">MONTO A PAGAR Bs.</td>
                <td class="text-right bold">{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="4" class="text-right" style="font-size: 9px;">IMPORTE BASE CRÉDITO FISCAL:</td>
                <td class="text-right" style="font-size: 9px;">{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="5" class="text-left" style="background-color: #fafafa; padding: 6px;">
                    <strong>Son: </strong> {{ $literalAmount }}
                </td>
            </tr>
        </tfoot>
    </table>

    <table class="footer-table">
        <tr>
            <td class="footer-left">
                <p><strong>"ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO DE ÉSTA SERÁ SANCIONADO DE ACUERDO A LEY"</strong></p>
                <p>{{ $sale->leyenda ?? $config['leyenda'] ?? 'Ley N° 453: El proveedor debe exhibir el precio total de venta al público en moneda nacional.' }}</p>
                <p class="italic" style="font-size: 8.5px; margin-top: 5px;">{{ ($sale->codigo_emision ?? 1) == 2 ? 'Este documento es la representación gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación fuera de línea' : 'Este documento es la representación gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación en línea' }}</p>
            </td>
            <td class="footer-right">
                @if($qrCode)
                    <img src="data:image/svg+xml;base64,{{ $qrCode }}" class="qr-code">
                @endif
            </td>
        </tr>
    </table>
</body>
</html>

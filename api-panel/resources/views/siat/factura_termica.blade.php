<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Factura Térmica</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9.5px;
            margin: 0;
            padding: 2px 4px;
            width: 100%;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .mb-1 { margin-bottom: 4px; }
        .mt-1 { margin-top: 4px; }
        
        .header { margin-bottom: 8px; text-align: center; }
        .header h3 { margin: 0 0 3px 0; font-size: 13px; text-transform: uppercase; }
        .header-branch { font-size: 8.5px; line-height: 1.25; margin-bottom: 6px; }
        
        .tax-header {
            font-size: 9px;
            line-height: 1.3;
            text-align: center;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 5px 0;
            margin-bottom: 8px;
        }
        
        .cuf-title {
            font-weight: bold;
            font-size: 8px;
            margin-top: 2px;
        }
        
        .cuf-code {
            font-family: 'Courier', monospace, sans-serif;
            font-size: 7.5px;
            line-height: 1.15;
            word-break: break-all;
            word-wrap: break-word;
            text-align: center;
            margin: 2px 0 0 0;
            padding: 0 2px;
        }
        
        .client-info {
            font-size: 9px;
            line-height: 1.35;
            margin-bottom: 8px;
        }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; font-size: 9px; }
        th, td { padding: 3px 1px; }
        .items-table thead th {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            font-size: 8.5px;
        }
        .items-table tbody td {
            border-bottom: 1px dotted #ccc;
            vertical-align: top;
        }
        
        .totals-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .totals-table td { border: none; padding: 2px 1px; }
        
        .literal-box {
            font-size: 8.5px;
            margin: 6px 0;
            padding: 4px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            text-transform: uppercase;
        }
        
        .qr-container { padding: 6px 0; text-align: center; }
        .qr-code { width: 115px; height: 115px; display: inline-block; }
        
        .leyenda {
            font-size: 7.5px;
            line-height: 1.25;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 6px;
        }
        .leyenda p { margin: 3px 0; }
        .italic { font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h3>{{ $config['razon_social'] ?? 'FARMACIA MISALUD' }}</h3>
        <div class="header-branch">
            <strong>{{ ($config['sucursal'] ?? 0) == 0 ? 'CASA MATRIZ' : 'SUCURSAL Nº ' . $config['sucursal'] }}</strong><br>
            No. Punto de Venta: {{ $config['punto_venta'] ?? 0 }}<br>
            {{ $config['direccion'] ?? 'Av. Principal #123 (Zona Norte)' }}<br>
            Tel. {{ $config['telefono'] ?? '0' }}<br>
            {{ $config['municipio'] ?? 'Santa Cruz' }} - BOLIVIA
        </div>
    </div>

    <div class="tax-header">
        <strong>NIT:</strong> {{ $config['nit'] ?? '6789406016' }}<br>
        <strong>FACTURA N°:</strong> {{ $sale->invoice_number }}<br>
        @if(($sale->codigo_emision ?? 1) == 2)
            <div style="font-size: 8px; font-weight: bold; border: 1px dashed #000; padding: 2px; margin: 3px 0;">(FUERA DE LÍNEA - CONTINGENCIA)</div>
        @endif
        @if(!empty($sale->cafc))
            <div style="font-size: 8px; font-weight: bold; margin-top: 2px;">N° CAFC: {{ $sale->cafc }}</div>
        @endif
        <div class="cuf-title">CÓD. AUTORIZACIÓN (CUF):</div>
        <div class="cuf-code">{{ $sale->cuf }}</div>
    </div>

    <div class="client-info">
        <strong>FECHA:</strong> {{ $dateFormatted }}<br>
        <strong>SEÑOR(ES):</strong> {{ $sale->business_name }}<br>
        <strong>NIT/CI/CEX:</strong> {{ $sale->nit_ci }}{{ $sale->customer?->complement ? '-' . $sale->customer->complement : '' }}<br>
        <strong>CÓD. CLIENTE:</strong> {{ $sale->customer_id ?? $sale->nit_ci ?? '0' }}<br>
        @if($sale->payment_method_code == 10)
        <strong>MEDIO DE PAGO:</strong> COMBINADO<br>
        &nbsp;&bull; Efectivo: Bs. {{ number_format(max(0, $sale->total_amount - ($sale->card_amount ?? 0)), 2) }}<br>
        &nbsp;&bull; Tarjeta: Bs. {{ number_format($sale->card_amount ?? 0, 2) }} (N°: {{ $sale->card_number ?? 'XXXX00000000XXXX' }})<br>
        @elseif($sale->payment_method_code == 2 || !empty($sale->card_number))
        <strong>MEDIO DE PAGO:</strong> TARJETA - N°: {{ $sale->card_number ?? 'XXXX00000000XXXX' }}<br>
        @elseif($sale->payment_method_code == 7)
        <strong>MEDIO DE PAGO:</strong> QR / TRANSFERENCIA BANCARIA<br>
        @elseif($sale->payment_method_code == 27)
        <strong>MEDIO DE PAGO:</strong> GIFT CARD (VALE DE COMPRA){{ $sale->gift_card_code ? ' - N°: ' . $sale->gift_card_code : '' }}<br>
        @elseif($sale->payment_method_code == 35)
        <strong>MEDIO DE PAGO:</strong> PAGO POSTERIOR (VENTA A CRÉDITO)<br>
        @endif
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th width="15%" class="text-center">CANT</th>
                <th width="53%" class="text-left">DETALLE</th>
                <th width="16%" class="text-right">P.U.</th>
                <th width="16%" class="text-right">SUBT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
            <tr>
                <td class="text-center">{{ number_format($item->quantity, 0) }}</td>
                <td>
                    {{ $item->product->name }}
                    @if(($item->discount ?? 0) > 0)
                        <br><span style="font-size: 8px;">Desc. Bs. -{{ number_format($item->discount, 2) }}</span>
                    @endif
                </td>
                <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($item->subtotal ?? ($item->quantity * $item->unit_price), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $grossSubtotal = $sale->items->sum(fn($i) => (float) $i->quantity * (float) $i->unit_price);
        $itemDiscounts = (float) $sale->items->sum('discount');
        $totalDiscount = $itemDiscounts + (float) ($sale->additional_discount ?? 0);
        $giftCardAmt   = (float) ($sale->gift_card_amount ?? 0);
        $montoAPagar   = max(0, (float) $sale->total_amount - $giftCardAmt);
    @endphp
    <table class="totals-table">
        <tr>
            <td class="text-right">SUBTOTAL Bs.</td>
            <td width="35%" class="text-right">{{ number_format($grossSubtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right">DESCUENTO Bs.</td>
            <td class="text-right">{{ number_format($totalDiscount, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right bold">TOTAL Bs.</td>
            <td width="35%" class="text-right bold">{{ number_format($sale->total_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right">MONTO GIFT CARD Bs.</td>
            <td class="text-right">{{ number_format($giftCardAmt, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right bold">MONTO A PAGAR Bs.</td>
            <td class="text-right bold">{{ number_format($montoAPagar, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right" style="font-size: 8px;">IMPORTE BASE CRÉD. FISCAL:</td>
            <td class="text-right" style="font-size: 8px;">{{ number_format($montoAPagar, 2) }}</td>
        </tr>
    </table>

    <div class="literal-box">
        <strong>Son: </strong> {{ $literalAmount }}
    </div>

    <div class="qr-container">
        @if($qrCode)
            <img src="data:image/svg+xml;base64,{{ $qrCode }}" class="qr-code">
        @endif
    </div>

    <div class="leyenda">
        <p class="bold">"ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO DE ÉSTA SERÁ SANCIONADO DE ACUERDO A LEY"</p>
        <p>{{ $sale->leyenda ?? $config['leyenda'] ?? 'Ley N° 453: El proveedor debe exhibir el precio total de venta al público en moneda nacional.' }}</p>
        <p class="italic">{{ ($sale->codigo_emision ?? 1) == 2 ? 'Este documento es la representación gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación fuera de línea' : 'Este documento es la representación gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación en línea' }}</p>
    </div>
</body>
</html>

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
                <h2 style="margin: 0 0 5px 0; font-size: 15px; text-transform: uppercase;">{{ $config['razon_social'] ?? 'LIMA ALBERTH MAYRON' }}</h2>
                <p style="margin: 0; line-height: 1.35; font-size: 10px;">
                    <strong>{{ ($config['sucursal'] ?? 0) == 0 ? 'CASA MATRIZ' : 'SUCURSAL N. ' . $config['sucursal'] }}</strong><br>
                    No. Punto de Venta {{ $config['punto_venta'] ?? 0 }}<br>
                    {{ $config['direccion'] ?? 'Av. Principal 123' }}<br>
                    Teléfono: {{ $config['telefono'] ?? '2222222' }}<br>
                    {{ $config['municipio'] ?? 'La Paz' }}
                </p>
            </td>
            <td class="header-right">
                <div class="box">
                    <strong>NIT:</strong> {{ $config['nit'] ?? '6789406016' }}<br>
                    <strong>FACTURA N°:</strong> {{ $sale->invoice_number }}<br>
                    <strong>CÓD. AUTORIZACIÓN:</strong><br>
                    <div class="cuf-box">{{ $sale->cuf }}</div>
                    @if(!empty($sale->cafc))
                        <div style="margin-top: 3px;"><strong>N° CAFC:</strong> {{ $sale->cafc }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="text-center" style="margin-bottom: 12px;">
        <div style="font-size: 15px; font-weight: bold; letter-spacing: 0.5px;">FACTURA</div>
        <div style="font-size: 10.5px;">(Con Derecho a Crédito Fiscal)</div>
        @if(($sale->codigo_emision ?? 1) == 2)
            <div style="font-size: 9px; font-weight: bold; color: #b71c1c; margin-top: 2px;">(EMITIDA EN CONTINGENCIA - FUERA DE LÍNEA)</div>
        @endif
    </div>

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

    @php
        $groupedRows = [];
        foreach ($sale->items as $item) {
            $k = $item->product_id . '_' . number_format((float) $item->unit_price, 2, '.', '');
            if (!isset($groupedRows[$k])) {
                $groupedRows[$k] = [
                    'code'       => (string) ($item->product->id ?? $item->product_id),
                    'name'       => $item->product->name ?? 'Producto',
                    'uom'        => 'UNIDAD (BIENES)',
                    'quantity'   => 0.0,
                    'unit_price' => (float) $item->unit_price,
                    'discount'   => 0.0,
                ];
            }
            $groupedRows[$k]['quantity'] += (float) $item->quantity;
            $groupedRows[$k]['discount'] = round($groupedRows[$k]['discount'] + (float) ($item->discount ?? 0), 2);
        }
        $sumSubtotals = 0.0;
        foreach ($groupedRows as &$gr) {
            $gr['subtotal'] = round(($gr['quantity'] * $gr['unit_price']) - $gr['discount'], 2);
            $sumSubtotals = round($sumSubtotals + $gr['subtotal'], 2);
        }
        unset($gr);
        $additionalDiscount = round((float) ($sale->additional_discount ?? 0), 2);
        $giftCardAmt        = round((float) ($sale->gift_card_amount ?? 0), 2);
        $montoAPagar        = round(max(0, (float) $sale->total_amount - $giftCardAmt), 2);
    @endphp

    <table class="items-table">
        <thead>
            <tr>
                <th width="12%" class="text-center">CÓDIGO<br>PRODUCTO /<br>SERVICIO</th>
                <th width="9%" class="text-center">CANTIDAD</th>
                <th width="13%" class="text-center">UNIDAD DE<br>MEDIDA</th>
                <th width="34%" class="text-center">DESCRIPCIÓN</th>
                <th width="10%" class="text-center">PRECIO<br>UNITARIO</th>
                <th width="10%" class="text-center">DESCUENTO</th>
                <th width="12%" class="text-center">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groupedRows as $row)
            <tr>
                <td class="text-left">{{ $row['code'] }}</td>
                <td class="text-right">{{ number_format($row['quantity'], 2) }}</td>
                <td class="text-left">{{ $row['uom'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td class="text-right">{{ number_format($row['unit_price'], 2) }}</td>
                <td class="text-right">{{ number_format($row['discount'], 2) }}</td>
                <td class="text-right">{{ number_format($row['subtotal'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="text-right bold">SUBTOTAL Bs</td>
                <td class="text-right bold">{{ number_format($sumSubtotals, 2) }}</td>
            </tr>
            <tr>
                <td colspan="6" class="text-right bold">DESCUENTO Bs</td>
                <td class="text-right bold">{{ number_format($additionalDiscount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="6" class="text-right bold">TOTAL Bs</td>
                <td class="text-right bold">{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="6" class="text-right bold">MONTO GIFT CARD Bs</td>
                <td class="text-right bold">{{ number_format($giftCardAmt, 2) }}</td>
            </tr>
            <tr>
                <td colspan="6" class="text-right bold">MONTO A PAGAR Bs</td>
                <td class="text-right bold">{{ number_format($montoAPagar, 2) }}</td>
            </tr>
            <tr>
                <td colspan="6" class="text-right bold" style="font-size: 9px;">IMPORTE BASE CRÉDITO FISCAL Bs</td>
                <td class="text-right bold" style="font-size: 9px;">{{ number_format($montoAPagar, 2) }}</td>
            </tr>
            <tr>
                <td colspan="7" class="text-left" style="background-color: #fafafa; padding: 6px;">
                    <strong>Son: </strong> {{ $literalAmount }}
                </td>
            </tr>
        </tfoot>
    </table>

    <table class="footer-table">
        <tr>
            <td class="footer-left">
                <p><strong>ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY</strong></p>
                <p>{{ $sale->leyenda ?? $config['leyenda'] ?? 'Ley N° 453: El proveedor debe exhibir el precio total de venta al público en moneda nacional.' }}</p>
                <p class="italic" style="font-size: 8.5px; margin-top: 5px;">"{{ ($sale->codigo_emision ?? 1) == 2 ? 'Este documento es la Representación Gráfica de un Documento Fiscal Digital emitido fuera de línea, verifique su envío con su proveedor o en la página web www.impuestos.gob.bo' : 'Este documento es la Representación Gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación en línea' }}"</p>
                <p style="font-size: 9px; margin-top: 8px;">(Factura Compra Venta)</p>
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

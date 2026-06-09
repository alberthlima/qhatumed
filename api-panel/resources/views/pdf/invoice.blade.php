<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura SIAT - {{ $sale->invoice_number }}</title>
    <style>
        @page { margin: 1cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #333; line-height: 1.4; }
        .header { width: 100%; margin-bottom: 20px; }
        .company-info { width: 50%; float: left; text-align: left; }
        .invoice-box { width: 45%; float: right; border: 1px solid #000; padding: 10px; text-align: center; }
        .clear { clear: both; }
        
        .company-name { font-weight: bold; font-size: 12pt; text-transform: uppercase; margin-bottom: 5px; }
        .company-details { font-size: 8pt; color: #666; }
        
        .client-info { width: 100%; border: 1px solid #ddd; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .client-title { font-weight: bold; margin-bottom: 5px; color: #555; text-transform: uppercase; font-size: 9pt; }
        
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th { background: #f5f5f5; border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 9pt; }
        .table td { border: 1px solid #ddd; padding: 8px; font-size: 9pt; }
        .text-right { text-align: right; }
        
        .totals { width: 40%; float: right; margin-top: 10px; }
        .total-row { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 5px 0; }
        .grand-total { font-size: 12pt; font-weight: bold; color: #2c3e50; border-top: 2px solid #2c3e50; margin-top: 5px; padding-top: 5px; }
        
        .footer { width: 100%; margin-top: 40px; text-align: center; font-size: 8pt; page-break-inside: avoid; }
        .qr-section { margin-top: 20px; }
        .legend { font-style: italic; color: #777; margin-top: 10px; font-size: 8pt; }
        .siat-msg { font-weight: bold; margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 10px; }
        
        .cuf-box { font-family: 'Courier New', Courier, monospace; font-size: 8pt; background: #f9f9f9; padding: 5px; border: 1px dotted #ccc; margin-top: 5px; display: block; word-break: break-all; }
    </style>
</head>
<body>

    <div class="header">
        <div class="company-info">
            <div class="company-name">{{ $config['razon_social'] }}</div>
            <div class="company-details">
                Casa Matriz<br>
                {{ $config['direccion'] }}<br>
                {{ $config['municipio'] }} - Bolivia<br>
                Punto de Venta: {{ $config['punto_venta'] }}
            </div>
        </div>
        <div class="invoice-box">
            <div style="font-weight: bold; font-size: 14pt; margin-bottom: 5px;">FACTURA</div>
            <div style="font-size: 9pt;">(Con Derecho a Crédito Fiscal)</div>
            <hr>
            <table style="width: 100%; font-size: 9pt;">
                <tr><td class="text-right"><strong>NIT:</strong></td><td>{{ $config['nit'] }}</td></tr>
                <tr><td class="text-right"><strong>Nº FACTURA:</strong></td><td>{{ $sale->invoice_number }}</td></tr>
                <tr><td class="text-right"><strong>Nº AUTORIZACIÓN:</strong></td><td>SIAT Online</td></tr>
            </table>
        </div>
        <div class="clear"></div>
    </div>

    <div class="client-info">
        <table style="width: 100%; font-size: 9pt;">
            <tr>
                <td width="15%"><strong>LUGAR Y FECHA:</strong></td>
                <td>{{ $config['municipio'] }}, {{ $sale->sale_date->format('d/m/Y H:i') }}</td>
                <td width="15%" class="text-right"><strong>NIT/CI:</strong></td>
                <td width="20%">{{ $sale->nit_ci }}</td>
            </tr>
            <tr>
                <td><strong>NOMBRE:</strong></td>
                <td colspan="3">{{ $sale->business_name }}</td>
            </tr>
        </table>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th width="10%">CÓDIGO</th>
                <th width="10%">CANT</th>
                <th>DESCRIPCIÓN</th>
                <th width="15%" class="text-right">P. UNIT.</th>
                <th width="15%" class="text-right">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
            <tr>
                <td>{{ $item->product->id }}</td>
                <td>{{ number_format($item->quantity, 2) }}</td>
                <td>{{ $item->product->name }}</td>
                <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($item->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="width: 100%;">
        <div style="width: 55%; float: left; font-size: 9pt;">
            <strong>SON:</strong> {{ $literalAmount }}
        </div>
        <div class="totals">
            <table style="width: 100%; font-size: 10pt;">
                <tr>
                    <td class="text-right">SUBTOTAL Bs.</td>
                    <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-right">DESCUENTO Bs.</td>
                    <td class="text-right">0.00</td>
                </tr>
                <tr class="grand-total">
                    <td class="text-right">TOTAL Bs.</td>
                    <td class="text-right">{{ number_format($sale->total_amount, 2) }}</td>
                </tr>
            </table>
        </div>
        <div class="clear"></div>
    </div>

    <div class="footer">
        <div class="legend">
            ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO DE ÉSTA SERÁ SANCIONADO DE ACUERDO A LA LEY
        </div>
        <div class="legend" style="margin-top: 5px;">
            {{ $config['leyenda'] ?? 'Ley N° 453: El proveedor de servicios debe prioritariamente informar sobre las características de los productos.' }}
        </div>
        
        <div class="qr-section">
            <img src="{{ $qrImage }}" width="120">
        </div>

        <div class="siat-msg">
            "Este documento es la representación gráfica de una Factura Digital emitida en una modalidad de facturación en línea"
        </div>

        <div style="margin-top: 10px;">
            <strong>CUF:</strong>
            <span class="cuf-box">{{ $sale->cuf }}</span>
        </div>
    </div>

</body>
</html>

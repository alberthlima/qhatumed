<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Factura Térmica</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
            width: 100%;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .mb-1 { margin-bottom: 5px; }
        .mt-1 { margin-top: 5px; }
        
        .header { margin-bottom: 15px; }
        .header h3 { margin: 0; font-size: 14px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border-bottom: 1px dashed #000; padding: 4px 0; }
        th { text-align: left; }
        
        .totals-table th, .totals-table td { border: none; padding: 2px 0; }
        
        .qr-container { padding: 10px 0; }
        .qr-code { width: 120px; height: 120px; }
        
        .leyenda { font-size: 8px; text-align: center; margin-top: 15px; border-top: 1px solid #000; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header text-center">
        <h3>FARMACIA MISALUD</h3>
        <p class="mb-1">
            <strong>NIT:</strong> {{ config('siat.nit') }}<br>
            <strong>FACTURA N°:</strong> {{ $sale->invoice_number }}<br>
            <strong>CUF:</strong> {{ $sale->cuf }}
        </p>
        <p class="mb-1">
            {{ config('siat.direccion') ?? 'Av. Principal #123' }}<br>
            {{ config('siat.municipio') ?? 'Santa Cruz, Bolivia' }}
        </p>
    </div>

    <div class="mb-1">
        <strong>FECHA:</strong> {{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y H:i') }}<br>
        <strong>SEÑOR(ES):</strong> {{ $sale->business_name }}<br>
        <strong>NIT/CI:</strong> {{ $sale->nit_ci }}
    </div>

    <table>
        <thead>
            <tr>
                <th width="15%">CANT</th>
                <th width="55%">DETALLE</th>
                <th width="15%" class="text-right">P.U.</th>
                <th width="15%" class="text-right">SUBT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
            <tr>
                <td class="text-center">{{ $item->quantity }}</td>
                <td>{{ $item->product->name }}</td>
                <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($item->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td class="text-right bold">TOTAL Bs.</td>
            <td width="30%" class="text-right bold">{{ number_format($sale->total_amount, 2) }}</td>
        </tr>
    </table>

    <div class="text-center qr-container">
        @if($qrCode)
            <img src="data:image/svg+xml;base64,{{ $qrCode }}" class="qr-code">
        @endif
    </div>

    <div class="leyenda">
        "ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO DE ÉSTA SERÁ SANCIONADO DE ACUERDO A LEY"<br>
        Ley N° 453: El proveedor de servicios debe prioritariamente informar sobre las características de los productos.
    </div>
</body>
</html>

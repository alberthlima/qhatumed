<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Factura Carta</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; margin: 30px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        
        .header-table { width: 100%; margin-bottom: 20px; }
        .header-left { width: 50%; vertical-align: top; }
        .header-right { width: 50%; vertical-align: top; text-align: right; }
        
        .box { border: 1px solid #000; padding: 10px; border-radius: 5px; }
        .box h3 { margin: 0 0 5px 0; font-size: 16px; }
        
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 4px; border: 1px solid #ddd; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 8px; }
        .items-table th { background-color: #f2f2f2; }
        
        .footer-table { width: 100%; }
        .footer-left { width: 70%; vertical-align: top; font-size: 10px; padding-right: 20px; text-align: center; }
        .footer-right { width: 30%; vertical-align: top; text-align: center;}
        
        .qr-code { width: 130px; height: 130px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="header-left">
                <h2>FARMACIA MISALUD</h2>
                <p>
                    {{ config('siat.direccion') ?? 'Av. Principal #123' }}<br>
                    Teléfono: {{ config('siat.telefono') ?? '0' }}<br>
                    {{ config('siat.municipio') ?? 'Santa Cruz, Bolivia' }}
                </p>
            </td>
            <td class="header-right">
                <div class="box">
                    <h3>FACTURA</h3>
                    <strong>NIT:</strong> {{ config('siat.nit') }}<br>
                    <strong>N° FACTURA:</strong> {{ $sale->invoice_number }}<br>
                    <strong>CUF:</strong> {{ $sale->cuf }}
                </div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td width="15%" class="bold">FECHA:</td>
            <td width="35%">{{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y H:i') }}</td>
            <td width="15%" class="bold">NIT/CI:</td>
            <td width="35%">{{ $sale->nit_ci }}</td>
        </tr>
        <tr>
            <td class="bold">SEÑOR(ES):</td>
            <td colspan="3">{{ $sale->business_name }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="10%">CANTIDAD</th>
                <th width="50%">DESCRIPCIÓN</th>
                <th width="15%">P. UNITARIO</th>
                <th width="10%">DESCUENTO</th>
                <th width="15%">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
            <tr>
                <td class="text-center">{{ $item->quantity }}</td>
                <td>{{ $item->product->name }}</td>
                <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format($item->discount ?? 0, 2) }}</td>
                <td class="text-right">{{ number_format($item->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right bold">TOTAL Bs.</td>
                <td class="text-right bold">{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="5" class="text-left">
                    <strong>Son: </strong> {{-- Here we could use a number-to-words library if we had one --}} 
                    {{ number_format($sale->total_amount, 2) }} Bolivianos
                </td>
            </tr>
        </tfoot>
    </table>

    <table class="footer-table">
        <tr>
            <td class="footer-left">
                <p>"ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO DE ÉSTA SERÁ SANCIONADO DE ACUERDO A LEY"</p>
                <p>Ley N° 453: El proveedor de servicios debe prioritariamente informar sobre las características de los productos.</p>
                <br>
                <p><strong>CÓDIGO DE CONTROL:</strong> {{-- SIAT ya no usa código de control como antes, todo es CUF, pero se deja por estructura si se llegara a usar --}}</p>
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

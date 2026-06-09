<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nota de Ingreso - COMPRA #{{ $purchase->id }}</title>
    <style>
        @page { margin: 1cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #333; line-height: 1.4; }
        .header { width: 100%; margin-bottom: 20px; }
        .company-info { width: 50%; float: left; text-align: left; }
        .title-box { width: 45%; float: right; border: 1px solid #2c3e50; padding: 10px; text-align: center; background: #fdfdfd; }
        .clear { clear: both; }
        
        .company-name { font-weight: bold; font-size: 12pt; text-transform: uppercase; color: #2c3e50; margin-bottom: 5px; }
        .company-details { font-size: 8pt; color: #666; }
        
        .info-section { width: 100%; border: 1px solid #ddd; padding: 10px; margin-bottom: 20px; border-radius: 5px; background: #fafafa; }
        .info-table { width: 100%; font-size: 9pt; }
        
        .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table th { background: #2c3e50; color: #ffffff; border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 9pt; }
        .table td { border: 1px solid #ddd; padding: 8px; font-size: 8.5pt; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .totals { width: 40%; float: right; margin-top: 10px; }
        .grand-total { font-size: 12pt; font-weight: bold; color: #2c3e50; border-top: 2px solid #2c3e50; margin-top: 5px; padding-top: 5px; }
        
        .footer { width: 100%; margin-top: 40px; text-align: center; font-size: 8pt; page-break-inside: avoid; }
        .signature-section { margin-top: 50px; width: 100%; }
        .signature-box { width: 40%; float: left; text-align: center; border-top: 1px solid #999; padding-top: 5px; margin: 0 5%; font-size: 9pt; }
    </style>
</head>
<body>

    <div class="header">
        <div class="company-info">
            <div class="company-name">{{ $config['razon_social'] }}</div>
            <div class="company-details">
                NIT: {{ $config['nit'] }}<br>
                Casa Matriz<br>
                {{ $config['direccion'] }}<br>
                {{ $config['municipio'] }} - Bolivia
            </div>
        </div>
        <div class="title-box">
            <div style="font-weight: bold; font-size: 12pt; color: #2c3e50; margin-bottom: 5px; text-transform: uppercase;">NOTA DE INGRESO</div>
            <div style="font-size: 9pt; font-weight: bold; color: #e74c3c;">Nº {{ str_pad($purchase->id, 6, '0', STR_PAD_LEFT) }}</div>
            <hr style="border: 0; border-top: 1px solid #ddd; margin: 8px 0;">
            <table style="width: 100%; font-size: 9pt;">
                <tr><td class="text-right"><strong>Motivo:</strong></td><td>{{ $purchase->reason === 'INVENTARIO_INICIAL' ? 'INVENTARIO INICIAL' : 'COMPRA' }}</td></tr>
                <tr><td class="text-right"><strong>Fecha:</strong></td><td>{{ date('d/m/Y', strtotime($purchase->purchase_date)) }}</td></tr>
            </table>
        </div>
        <div class="clear"></div>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <td width="18%"><strong>Proveedor:</strong></td>
                <td width="42%">{{ $purchase->supplier ? $purchase->supplier->name : 'SIN PROVEEDOR (INVENTARIO INICIAL)' }}</td>
                <td width="18%"><strong>Factura Física:</strong></td>
                <td width="22%">{{ $purchase->invoice_number ?: 'S/N' }}</td>
            </tr>
            <tr>
                <td><strong>Registrado por:</strong></td>
                <td>{{ $purchase->user ? $purchase->user->name : 'Sistema' }}</td>
                <td><strong>Fecha Registro:</strong></td>
                <td>{{ $purchase->created_at->format('d/m/Y H:i') }}</td>
            </tr>
        </table>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th width="5%" class="text-center">#</th>
                <th>Código/Barras</th>
                <th>Producto / Medicamento</th>
                <th width="10%">Lote</th>
                <th width="12%">Vencimiento</th>
                <th width="8%" class="text-right">Cant.</th>
                <th width="12%" class="text-right">Costo Unit.</th>
                <th width="12%" class="text-right">P. Venta Sug.</th>
                <th width="12%" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchase->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->product->barcode ?: 'S/C' }}</td>
                <td><strong>{{ $item->product->name }}</strong></td>
                <td class="text-center">{{ $item->batch ?: 'N/A' }}</td>
                <td class="text-center">{{ $item->expiration_date ? date('d/m/Y', strtotime($item->expiration_date)) : 'N/A' }}</td>
                <td class="text-right">{{ $item->quantity }}</td>
                <td class="text-right">Bs. {{ number_format($item->unit_cost, 2) }}</td>
                <td class="text-right">Bs. {{ number_format($item->suggested_sale_price ?: $item->product->sale_price, 2) }}</td>
                <td class="text-right">Bs. {{ number_format($item->quantity * $item->unit_cost, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="width: 100%;">
        <div style="width: 55%; float: left; font-size: 9pt;">
            <div style="margin-top: 20px;">
                <strong>Estado:</strong> 
                <span style="font-weight: bold; color: {{ $purchase->status === 'COMPLETED' ? '#27ae60' : '#c0392b' }}">
                    {{ $purchase->status === 'COMPLETED' ? 'COMPLETADO' : 'ANULADO' }}
                </span>
            </div>
        </div>
        <div class="totals">
            <table style="width: 100%; font-size: 10pt;">
                <tr class="grand-total">
                    <td class="text-right">COSTO TOTAL:</td>
                    <td class="text-right">Bs. {{ number_format($purchase->total_amount, 2) }}</td>
                </tr>
            </table>
        </div>
        <div class="clear"></div>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            Entrega Conforme
        </div>
        <div class="signature-box">
            Recibido Conforme<br>
            {{ $purchase->user ? $purchase->user->name : 'Responsable' }}
        </div>
        <div class="clear"></div>
    </div>

    <div class="footer" style="margin-top: 60px;">
        <hr style="border: 0; border-top: 1px dashed #ccc; margin-bottom: 10px;">
        Este documento es un comprobante interno del registro de ingreso de inventario en {{ $config['razon_social'] }}.
    </div>

</body>
</html>

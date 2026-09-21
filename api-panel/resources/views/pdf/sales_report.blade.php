<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas</title>
    <style>
        @page { margin: 1cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 8.5pt; color: #333; line-height: 1.3; }
        .header { width: 100%; margin-bottom: 15px; border-bottom: 2px solid #2c3e50; padding-bottom: 10px; }
        .company-info { width: 50%; float: left; text-align: left; }
        .title-box { width: 45%; float: right; text-align: right; }
        .clear { clear: both; }
        
        .company-name { font-weight: bold; font-size: 11pt; text-transform: uppercase; color: #2c3e50; }
        .company-details { font-size: 7.5pt; color: #666; }
        
        .report-title { font-weight: bold; font-size: 13pt; color: #2c3e50; text-transform: uppercase; margin-bottom: 5px; }
        .report-date { font-size: 8pt; color: #666; }
        
        .filters-box { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 8px 10px; margin-bottom: 15px; }
        .filters-title { font-weight: bold; font-size: 8pt; color: #495057; margin-bottom: 4px; text-transform: uppercase; }
        .filters-grid { font-size: 8pt; color: #6c757d; }
        
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 15px; }
        .table th { background: #2c3e50; color: #ffffff; border: 1px solid #ddd; padding: 6px 4px; text-align: left; font-size: 8pt; }
        .table td { border: 1px solid #ddd; padding: 5px 4px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .badge { display: inline-block; padding: 2px 4px; border-radius: 3px; font-size: 7pt; font-weight: bold; text-transform: uppercase; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        
        .totals-box { width: 100%; border: 1px solid #ddd; background: #fdfdfd; padding: 10px; border-radius: 5px; margin-top: 10px; }
        .totals-table { width: 100%; font-size: 9.5pt; font-weight: bold; }
        .totals-table td { padding: 3px 0; }
        .text-success { color: #27ae60; }
        .text-primary { color: #2c3e50; }
    </style>
</head>
<body>

    <div class="header">
        <div class="company-info">
            <div class="company-name">{{ $company->legal_name ?? $company->trade_name ?? 'FARMACIA MISALUD' }}</div>
            <div class="company-details">
                NIT: {{ $company->nit ?? '6789406016' }}<br>
                Dirección: Av. Principal #123 (Zona Norte)<br>
                Santa Cruz - Bolivia
            </div>
        </div>
        <div class="title-box">
            <div class="report-title">Reporte de Ventas</div>
            <div class="report-date">Fecha emisión: {{ date('d/m/Y H:i') }}</div>
        </div>
        <div class="clear"></div>
    </div>

    <!-- Filters Summary -->
    <div class="filters-box">
        <div class="filters-title">Filtros Aplicados</div>
        <div class="filters-grid">
            <strong>Rango:</strong> 
            {{ $filters['start_date'] ? \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') : 'Desde el inicio' }} 
            - 
            {{ $filters['end_date'] ? \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') : 'Hasta hoy' }}
            
            @if($filters['type'])
                | <strong>Tipo:</strong> {{ $filters['type'] === 'invoice' ? 'Facturas' : 'Recibos' }}
            @endif
            @if($filters['customer'])
                | <strong>Cliente:</strong> {{ $filters['customer'] }}
            @endif
            @if($filters['user'])
                | <strong>Cajero:</strong> {{ $filters['user'] }}
            @endif
            @if($filters['status'])
                | <strong>Estado:</strong> {{ $filters['status'] }}
            @endif
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th width="3%" class="text-center">#</th>
                <th width="12%">Fecha</th>
                <th>Cliente</th>
                <th width="12%">NIT/CI</th>
                <th width="10%">Tipo</th>
                <th width="10%">Nro Fact.</th>
                <th width="13%">M. Pago</th>
                <th width="12%" class="text-right">Total</th>
                <th width="10%" class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sales as $index => $sale)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y H:i') }}</td>
                <td>
                    <strong>{{ $sale->customer ? $sale->customer->name : ($sale->business_name ?: 'S/N') }}</strong>
                </td>
                <td>{{ $sale->nit_ci ?: '-' }}</td>
                <td>{{ $sale->is_invoice ? 'Factura' : 'Recibo' }}</td>
                <td>{{ $sale->invoice_number ?: '-' }}</td>
                <td>
                    @if($sale->payment_method_code == 1)
                        Efectivo
                    @elseif($sale->payment_method_code == 2)
                        Tarjeta
                    @else
                        QR/Transf.
                    @endif
                </td>
                <td class="text-right">Bs. {{ number_format($sale->total_amount, 2) }}</td>
                <td class="text-center">
                    <span class="badge {{ $sale->status === 'COMPLETED' ? 'badge-success' : 'badge-danger' }}">
                        {{ $sale->status === 'COMPLETED' ? 'Completado' : 'Anulado' }}
                    </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">No se encontraron ventas para los criterios seleccionados.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals-box">
        <table class="totals-table">
            <tr>
                <td width="50%" class="text-primary">CANTIDAD DE TRANSACCIONES:</td>
                <td width="50%" class="text-right">{{ $sales->count() }}</td>
            </tr>
            <tr>
                <td class="text-primary">VENTAS TOTALES (COMPLETADAS):</td>
                <td class="text-success text-right" style="font-size: 11pt;">Bs. {{ number_format($totalAmount, 2) }}</td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 7.5pt; color: #777;">
        Este documento es un reporte oficial de ventas para la farmacia.
    </div>

</body>
</html>

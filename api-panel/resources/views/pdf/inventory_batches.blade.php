<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Lotes y Vencimientos</title>
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
        
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 15px; }
        .table th { background: #2c3e50; color: #ffffff; border: 1px solid #ddd; padding: 6px 4px; text-align: left; font-size: 8pt; }
        .table td { border: 1px solid #ddd; padding: 5px 4px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .status-badge { padding: 2px 5px; border-radius: 3px; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; }
        .status-expired { background: #fadbd8; color: #78281f; }
        .status-warning { background: #fdebd0; color: #7e5109; }
        .status-valid { background: #d4efdf; color: #196f3d; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>

    <div class="header">
        <div class="company-info">
            <div class="company-name">{{ $config['razon_social'] }}</div>
            <div class="company-details">
                NIT: {{ $config['nit'] }}<br>
                {{ $config['direccion'] }}<br>
                {{ $config['municipio'] }} - Bolivia
            </div>
        </div>
        <div class="title-box">
            <div class="report-title">Control de Lotes / FEFO</div>
            <div style="font-size: 9pt; font-weight: bold; color: #555;">Filtro: {{ $status === 'TODOS' ? 'Todos los Lotes' : ($status === 'VENCIDOS' ? 'Lotes Vencidos' : ($status === 'POR_VENCER' ? 'Lotes Próximos a Vencer (60 días)' : 'Lotes Vigentes')) }}</div>
            <div class="report-date">Fecha emisión: {{ date('d/m/Y H:i') }}</div>
        </div>
        <div class="clear"></div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th width="3%" class="text-center">#</th>
                <th width="10%">Código</th>
                <th>Producto / Medicamento</th>
                <th width="12%">Laboratorio</th>
                <th width="10%">Lote</th>
                <th width="10%" class="text-center">Vencimiento</th>
                <th width="15%" class="text-center">Estado / Resta</th>
                <th width="8%" class="text-right">Stock</th>
                <th width="10%" class="text-right">Costo C/U</th>
                <th width="10%" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @if($batches->count() === 0)
            <tr>
                <td colspan="10" class="text-center" style="padding: 20px; color: #777;">No se encontraron lotes activos bajo el criterio seleccionado.</td>
            </tr>
            @endif
            @foreach($batches as $index => $batch)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $batch->product->barcode ?: 'S/C' }}</td>
                <td><strong>{{ $batch->product->name }}</strong></td>
                <td>{{ $batch->product->laboratory ? $batch->product->laboratory->name : 'Genérico' }}</td>
                <td><strong>{{ $batch->batch ?: 'N/A' }}</strong></td>
                <td class="text-center">{{ $batch->expiration_date ? date('d/m/Y', strtotime($batch->expiration_date)) : 'S/V' }}</td>
                <td class="text-center">
                    @if(!$batch->expiration_date)
                        <span class="status-badge status-valid">Vigente (Sin Vencimiento)</span>
                    @elseif($batch->days_remaining < 0)
                        <span class="status-badge status-expired">Vencido (hace {{ abs($batch->days_remaining) }} d.)</span>
                    @elseif($batch->days_remaining <= 60)
                        <span class="status-badge status-warning">Por Vencer ({{ $batch->days_remaining }} d.)</span>
                    @else
                        <span class="status-badge status-valid">Vigente ({{ $batch->days_remaining }} d.)</span>
                    @endif
                </td>
                <td class="text-right font-bold">{{ $batch->current_stock }}</td>
                <td class="text-right">Bs. {{ number_format($batch->unit_cost, 2) }}</td>
                <td class="text-right font-bold">Bs. {{ number_format($batch->current_stock * $batch->unit_cost, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="width: 100%; margin-top: 15px;">
        <div style="width: 50%; float: left; font-size: 9pt;">
            <strong>Lotes Listados:</strong> {{ $batches->count() }} ítems.
        </div>
        <div style="width: 45%; float: right; text-align: right; font-size: 10pt; font-weight: bold; color: #2c3e50;">
            Valor Costo Total Lotes: Bs. {{ number_format($batches->sum(fn($b) => $b->current_stock * $b->unit_cost), 2) }}
        </div>
        <div class="clear"></div>
    </div>

    <div style="margin-top: 40px; text-align: center; font-size: 7.5pt; color: #777;">
        Este documento es un reporte interno de control físico de fechas de vencimiento de lotes en {{ $config['razon_social'] }}.
    </div>

</body>
</html>

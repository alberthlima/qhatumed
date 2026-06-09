<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario Valorizado</title>
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
            <div class="company-name">{{ $config['razon_social'] }}</div>
            <div class="company-details">
                NIT: {{ $config['nit'] }}<br>
                {{ $config['direccion'] }}<br>
                {{ $config['municipio'] }} - Bolivia
            </div>
        </div>
        <div class="title-box">
            <div class="report-title">Inventario Valorizado</div>
            <div style="font-size: 9pt; font-weight: bold; color: #555;">Valuación Bajo Método PEPS</div>
            <div class="report-date">Fecha emisión: {{ date('d/m/Y H:i') }}</div>
        </div>
        <div class="clear"></div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th width="3%" class="text-center">#</th>
                <th width="12%">Código</th>
                <th>Producto / Medicamento</th>
                <th width="15%">Laboratorio</th>
                <th width="8%" class="text-right">Stock</th>
                <th width="10%" class="text-right">Costo Prom.</th>
                <th width="10%" class="text-right">P. Venta</th>
                <th width="12%" class="text-right">Val. Costo</th>
                <th width="12%" class="text-right">Val. Venta</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $index => $prod)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $prod['barcode'] ?: 'S/C' }}</td>
                <td><strong>{{ $prod['name'] }}</strong></td>
                <td>{{ $prod['laboratory'] }}</td>
                <td class="text-right">{{ $prod['stock'] }}</td>
                <td class="text-right">Bs. {{ number_format($prod['average_cost'], 2) }}</td>
                <td class="text-right">Bs. {{ number_format($prod['sale_price'], 2) }}</td>
                <td class="text-right font-weight-bold">Bs. {{ number_format($prod['cost_valuation'], 2) }}</td>
                <td class="text-right">Bs. {{ number_format($prod['sale_valuation'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-box">
        <table class="totals-table">
            <tr>
                <td width="30%" class="text-primary">VALOR TOTAL COSTO (INVENTARIO):</td>
                <td width="20%">Bs. {{ number_format($totalCost, 2) }}</td>
                <td width="30%" class="text-success">INGRESOS POTENCIALES (VENTA):</td>
                <td width="20%" class="text-success">Bs. {{ number_format($totalSale, 2) }}</td>
            </tr>
            <tr>
                <td class="text-primary">MARGEN DE UTILIDAD ESTIMADO:</td>
                <td class="text-success">Bs. {{ number_format($totalSale - $totalCost, 2) }}</td>
                <td class="text-primary">RENTABILIDAD PROYECTADA:</td>
                <td class="text-success">{{ $totalCost > 0 ? number_format((($totalSale - $totalCost) / $totalCost) * 100, 2) : '0.00' }}%</td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 7.5pt; color: #777;">
        Este documento es un reporte interno del estado financiero del inventario físico en {{ $config['razon_social'] }}.
    </div>

</body>
</html>

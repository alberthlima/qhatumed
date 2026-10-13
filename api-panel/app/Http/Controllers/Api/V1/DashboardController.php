<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Obtiene el compilado de estadísticas para el Dashboard.
     */
    public function statistics(Request $request)
    {
        try {
            $now = Carbon::now();
            $startOfMonth = $now->copy()->startOfMonth();
            $endOfMonth = $now->copy()->endOfMonth();
            
            $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
            $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

            // 1. KPIs Financieros (Mes Actual vs Mes Anterior)
            
            // Ventas
            $salesCurrentMonth = DB::table('sales')
                ->where('status', 'COMPLETED')
                ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                ->sum('total_amount');

            $salesLastMonth = DB::table('sales')
                ->where('status', 'COMPLETED')
                ->whereBetween('sale_date', [$startOfLastMonth, $endOfLastMonth])
                ->sum('total_amount');

            // Compras
            $purchasesCurrentMonth = DB::table('purchases')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('total_amount');

            $purchasesLastMonth = DB::table('purchases')
                ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
                ->sum('total_amount');

            // Cantidad de Transacciones de Venta
            $txCurrentMonth = DB::table('sales')
                ->where('status', 'COMPLETED')
                ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                ->count();

            $txLastMonth = DB::table('sales')
                ->where('status', 'COMPLETED')
                ->whereBetween('sale_date', [$startOfLastMonth, $endOfLastMonth])
                ->count();

            // Ganancia Neta (Ventas - Costos de los productos vendidos)
            // Calculamos en base al costo promedio al momento de la venta guardado en sale_items
            $revenueCurrentMonth = DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->where('sales.status', 'COMPLETED')
                ->whereBetween('sales.sale_date', [$startOfMonth, $endOfMonth])
                ->sum(DB::raw('sale_items.subtotal - (sale_items.quantity * COALESCE(sale_items.average_cost_at_sale, 0))'));

            $revenueLastMonth = DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->where('sales.status', 'COMPLETED')
                ->whereBetween('sales.sale_date', [$startOfLastMonth, $endOfLastMonth])
                ->sum(DB::raw('sale_items.subtotal - (sale_items.quantity * COALESCE(sale_items.average_cost_at_sale, 0))'));

            // Variaciones porcentuales
            $salesDiff = $this->calculatePercentageDiff($salesCurrentMonth, $salesLastMonth);
            $purchasesDiff = $this->calculatePercentageDiff($purchasesCurrentMonth, $purchasesLastMonth);
            $txDiff = $this->calculatePercentageDiff($txCurrentMonth, $txLastMonth);
            $revenueDiff = $this->calculatePercentageDiff($revenueCurrentMonth, $revenueLastMonth);

            // 2. Tendencia diaria de los últimos 30 días (Ventas vs Compras)
            $last30Days = [];
            for ($i = 29; $i >= 0; $i--) {
                $day = Carbon::now()->subDays($i)->format('Y-m-d');
                $last30Days[$day] = [
                    'date' => Carbon::now()->subDays($i)->format('d/m'),
                    'sales' => 0,
                    'purchases' => 0
                ];
            }

            $dailySales = DB::table('sales')
                ->where('status', 'COMPLETED')
                ->where('sale_date', '>=', Carbon::now()->subDays(29)->startOfDay())
                ->groupBy(DB::raw('DATE(sale_date)'))
                ->select(DB::raw('DATE(sale_date) as date, SUM(total_amount) as total'))
                ->get();

            foreach ($dailySales as $ds) {
                if (isset($last30Days[$ds->date])) {
                    $last30Days[$ds->date]['sales'] = (float) $ds->total;
                }
            }

            $dailyPurchases = DB::table('purchases')
                ->where('created_at', '>=', Carbon::now()->subDays(29)->startOfDay())
                ->groupBy(DB::raw('DATE(created_at)'))
                ->select(DB::raw('DATE(created_at) as date, SUM(total_amount) as total'))
                ->get();

            foreach ($dailyPurchases as $dp) {
                if (isset($last30Days[$dp->date])) {
                    $last30Days[$dp->date]['purchases'] = (float) $dp->total;
                }
            }

            $seriesTrend = [
                'categories' => array_column(array_values($last30Days), 'date'),
                'sales' => array_column(array_values($last30Days), 'sales'),
                'purchases' => array_column(array_values($last30Days), 'purchases'),
            ];

            // 3. Distribución de Métodos de Pago del mes
            $payments = DB::table('sales')
                ->where('status', 'COMPLETED')
                ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                ->groupBy('payment_method_code')
                ->select('payment_method_code', DB::raw('SUM(total_amount) as total'))
                ->get();

            $paymentDistribution = [
                'cash' => 0,
                'card' => 0,
                'qr' => 0
            ];

            foreach ($payments as $p) {
                if ($p->payment_method_code == 1) {
                    $paymentDistribution['cash'] = (float) $p->total;
                } elseif ($p->payment_method_code == 2) {
                    $paymentDistribution['card'] = (float) $p->total;
                } else {
                    // QR / Transferencia (7 y otros)
                    $paymentDistribution['qr'] += (float) $p->total;
                }
            }

            // 4. Top 5 Productos más vendidos
            $topProducts = DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->where('sales.status', 'COMPLETED')
                ->whereBetween('sales.sale_date', [$startOfMonth, $endOfMonth])
                ->groupBy('sale_items.product_id', 'products.name')
                ->select('products.name', DB::raw('SUM(sale_items.quantity) as qty, SUM(sale_items.subtotal) as total'))
                ->orderByDesc('qty')
                ->limit(5)
                ->get();

            // 5. Top 5 Laboratorios con mayor venta
            $topLaboratories = DB::table('sale_items')
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                ->where('sales.status', 'COMPLETED')
                ->whereBetween('sales.sale_date', [$startOfMonth, $endOfMonth])
                ->groupBy('products.laboratory_id', 'laboratories.name')
                ->select('laboratories.name', DB::raw('SUM(sale_items.subtotal) as total'))
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            // 6. Alertas de inventario

            // Semáforo de Lotes por Vencer (sólo lotes con stock actual)
            $lotesCriticos = DB::table('purchase_items')
                ->where('current_stock', '>', 0)
                ->whereBetween('expiration_date', [Carbon::now(), Carbon::now()->addDays(30)])
                ->count();

            $lotesAlerta = DB::table('purchase_items')
                ->where('current_stock', '>', 0)
                ->whereBetween('expiration_date', [Carbon::now()->addDays(31), Carbon::now()->addDays(90)])
                ->count();

            $lotesVigentes = DB::table('purchase_items')
                ->where('current_stock', '>', 0)
                ->where('expiration_date', '>', Carbon::now()->addDays(90))
                ->count();

            $lotesVencidos = DB::table('purchase_items')
                ->where('current_stock', '>', 0)
                ->where('expiration_date', '<', Carbon::now())
                ->count();

            // Productos con stock crítico (bajo stock mínimo)
            $stockCriticoCount = DB::table('products')
                ->where(function ($q) {
                    $q->where('status', 1)->orWhere('status', 'ACTIVE');
                })
                ->where('stock', '>', 0)
                ->whereRaw('stock <= min_stock')
                ->count();

            // Lista detallada de productos bajo stock mínimo (para widget rápido)
            $criticalStockProducts = DB::table('products')
                ->where(function ($q) {
                    $q->where('status', 1)->orWhere('status', 'ACTIVE');
                })
                ->where('stock', '>', 0)
                ->whereRaw('stock <= min_stock')
                ->select('id', 'name', 'stock', 'min_stock')
                ->limit(10)
                ->get();


            // 7. Estado de Caja Activa (para el usuario actual)
            $userId = auth()->id() ?? 1;
            $activeSession = DB::table('cash_register_sessions')
                ->join('cash_registers', 'cash_register_sessions.cash_register_id', '=', 'cash_registers.id')
                ->join('users', 'cash_register_sessions.user_id', '=', 'users.id')
                ->where('cash_register_sessions.status', 'OPEN')
                ->where('cash_register_sessions.user_id', $userId)
                ->select(
                    'cash_register_sessions.id',
                    'cash_registers.name as register_name',
                    'users.name as user_name',
                    'cash_register_sessions.opening_amount',
                    'cash_register_sessions.opening_time'
                )
                ->first();

            $cashRegisterStatus = null;
            if ($activeSession) {
                // Calcular saldo actual de caja (Efectivo)
                // Saldo = opening_amount + ingresos efectivo - egresos efectivo
                $movements = DB::table('cash_movements')
                    ->where('cash_register_session_id', $activeSession->id)
                    ->where('payment_method', 'EFECTIVO')
                    ->select(
                        DB::raw("SUM(CASE WHEN type = 'IN' THEN amount ELSE 0 END) as total_in"),
                        DB::raw("SUM(CASE WHEN type = 'OUT' THEN amount ELSE 0 END) as total_out")
                    )
                    ->first();

                $currentBalance = (float) $activeSession->opening_amount + (float) ($movements->total_in ?? 0) - (float) ($movements->total_out ?? 0);

                $cashRegisterStatus = [
                    'active' => true,
                    'register_name' => $activeSession->register_name,
                    'user_name' => $activeSession->user_name,
                    'opening_amount' => (float) $activeSession->opening_amount,
                    'current_balance' => $currentBalance,
                    'opening_date' => $activeSession->opening_time
                ];
            } else {
                $cashRegisterStatus = [
                    'active' => false
                ];
            }

            return response()->json([
                'kpis' => [
                    'sales' => [
                        'value' => (float) $salesCurrentMonth,
                        'diff' => $salesDiff
                    ],
                    'purchases' => [
                        'value' => (float) $purchasesCurrentMonth,
                        'diff' => $purchasesDiff
                    ],
                    'transactions' => [
                        'value' => $txCurrentMonth,
                        'diff' => $txDiff
                    ],
                    'revenue' => [
                        'value' => (float) $revenueCurrentMonth,
                        'diff' => $revenueDiff
                    ]
                ],
                'trend' => $seriesTrend,
                'payments' => $paymentDistribution,
                'top_products' => $topProducts,
                'top_laboratories' => $topLaboratories,
                'inventory_alerts' => [
                    'batches' => [
                        'critical' => $lotesCriticos, // <=30d
                        'warning' => $lotesAlerta,   // 30-90d
                        'healthy' => $lotesVigentes, // >90d
                        'expired' => $lotesVencidos  // <hoy
                    ],
                    'stock_critical_count' => $stockCriticoCount,
                    'products' => $criticalStockProducts
                ],
                'cash_register' => $cashRegisterStatus
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al calcular estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcula la variación porcentual entre dos valores.
     */
    private function calculatePercentageDiff($current, $last)
    {
        if ($last <= 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $last) / $last) * 100, 2);
    }
}

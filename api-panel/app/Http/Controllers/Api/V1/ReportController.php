<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\CompanySetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Obtiene el listado de ventas filtrado para la grilla.
     */
    public function sales(Request $request)
    {
        try {
            $query = $this->buildSalesQuery($request);

            $totalAmount = (float) $query->clone()->where('status', 'COMPLETED')->sum('total_amount');
            $totalCount = $query->clone()->count();
            
            $sales = $query->orderBy('sale_date', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'totals' => [
                    'amount' => $totalAmount,
                    'count' => $totalCount
                ],
                'data' => $sales
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener reporte de ventas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descarga el PDF de ventas.
     */
    public function downloadSalesPdf(Request $request)
    {
        try {
            $query = $this->buildSalesQuery($request);
            $sales = $query->orderBy('sale_date', 'asc')->get();
            $totalAmount = $sales->where('status', 'COMPLETED')->sum('total_amount');
            
            $company = CompanySetting::first();
            
            $customerName = null;
            if ($request->filled('customer_id')) {
                $customer = \App\Models\Customer::find($request->customer_id);
                $customerName = $customer ? $customer->name : null;
            }

            $userName = null;
            if ($request->filled('user_id')) {
                $user = \App\Models\User::find($request->user_id);
                $userName = $user ? $user->name : null;
            }

            $pdf = Pdf::loadView('pdf.sales_report', [
                'sales' => $sales,
                'totalAmount' => $totalAmount,
                'company' => $company,
                'filters' => [
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'type' => $request->type,
                    'customer' => $customerName,
                    'user' => $userName,
                    'status' => $request->status
                ]
            ]);

            $pdf->setPaper('letter', 'portrait');

            return response($pdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="reporte_ventas_' . date('Ymd_His') . '.pdf"');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF de ventas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descarga el CSV/Excel de ventas.
     */
    public function downloadSalesExcel(Request $request)
    {
        try {
            $query = $this->buildSalesQuery($request);

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="reporte_ventas_' . date('Ymd_His') . '.csv"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0'
            ];

            $callback = function() use ($query) {
                $file = fopen('php://output', 'w');
                fwrite($file, "\xEF\xBB\xBF"); // BOM para Excel
                
                fputcsv($file, [
                    'ID Venta', 'Fecha', 'Cliente', 'NIT/CI', 'Tipo', 'Factura Nro', 'CUF', 'Método Pago', 'Total (Bs)', 'Cajero', 'Estado'
                ], ';');

                $query->orderBy('sale_date', 'asc')->chunk(200, function($sales) use ($file) {
                    foreach ($sales as $sale) {
                        $paymentMethod = 'Efectivo';
                        if ($sale->payment_method_code == 2) {
                            $paymentMethod = 'Tarjeta';
                        } elseif ($sale->payment_method_code != 1) {
                            $paymentMethod = 'QR/Transferencia';
                        }
                        
                        fputcsv($file, [
                            $sale->id,
                            $sale->sale_date,
                            $sale->customer ? $sale->customer->name : ($sale->business_name ?: 'S/N'),
                            $sale->nit_ci,
                            $sale->is_invoice ? 'Factura' : 'Recibo',
                            $sale->invoice_number ?: '-',
                            $sale->cuf ?: '-',
                            $paymentMethod,
                            number_format($sale->total_amount, 2, '.', ''),
                            $sale->user ? $sale->user->name : '-',
                            $sale->status
                        ], ';');
                    }
                });

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar CSV de ventas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el listado de compras filtrado para la grilla.
     */
    public function purchases(Request $request)
    {
        try {
            $query = $this->buildPurchasesQuery($request);

            $totalAmount = (float) $query->clone()->where('status', 'COMPLETED')->sum('total_amount');
            $totalCount = $query->clone()->count();

            $purchases = $query->orderBy('purchase_date', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'totals' => [
                    'amount' => $totalAmount,
                    'count' => $totalCount
                ],
                'data' => $purchases
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener reporte de compras: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descarga el PDF de compras.
     */
    public function downloadPurchasesPdf(Request $request)
    {
        try {
            $query = $this->buildPurchasesQuery($request);
            $purchases = $query->orderBy('purchase_date', 'asc')->get();
            $totalAmount = $purchases->where('status', 'COMPLETED')->sum('total_amount');
            
            $company = CompanySetting::first();
            
            $supplierName = null;
            if ($request->filled('supplier_id')) {
                $supplier = \App\Models\Supplier::find($request->supplier_id);
                $supplierName = $supplier ? $supplier->company_name : null;
            }

            $pdf = Pdf::loadView('pdf.purchases_report', [
                'purchases' => $purchases,
                'totalAmount' => $totalAmount,
                'company' => $company,
                'filters' => [
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'supplier' => $supplierName,
                    'status' => $request->status
                ]
            ]);

            $pdf->setPaper('letter', 'portrait');

            return response($pdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="reporte_compras_' . date('Ymd_His') . '.pdf"');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF de compras: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descarga el CSV/Excel de compras.
     */
    public function downloadPurchasesExcel(Request $request)
    {
        try {
            $query = $this->buildPurchasesQuery($request);

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="reporte_compras_' . date('Ymd_His') . '.csv"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0'
            ];

            $callback = function() use ($query) {
                $file = fopen('php://output', 'w');
                fwrite($file, "\xEF\xBB\xBF"); // BOM para Excel
                
                fputcsv($file, [
                    'ID Compra', 'Fecha Compra', 'Proveedor', 'Factura Nro', 'Total (Bs)', 'Registrado Por', 'Estado', 'Detalle'
                ], ';');

                $query->orderBy('purchase_date', 'asc')->chunk(200, function($purchases) use ($file) {
                    foreach ($purchases as $purchase) {
                        fputcsv($file, [
                            $purchase->id,
                            $purchase->purchase_date,
                            $purchase->supplier ? $purchase->supplier->company_name : '-',
                            $purchase->invoice_number ?: '-',
                            number_format($purchase->total_amount, 2, '.', ''),
                            $purchase->user ? $purchase->user->name : '-',
                            $purchase->status,
                            $purchase->reason ?: '-'
                        ], ';');
                    }
                });

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar CSV de compras: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Construye la consulta de ventas aplicando filtros.
     */
    private function buildSalesQuery(Request $request)
    {
        $query = Sale::with(['customer', 'user']);

        if ($request->filled('start_date')) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }
        if ($request->filled('type')) {
            if ($request->type === 'invoice') {
                $query->where('is_invoice', true);
            } elseif ($request->type === 'receipt') {
                $query->where('is_invoice', false);
            }
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $query;
    }

    /**
     * Construye la consulta de compras aplicando filtros.
     */
    private function buildPurchasesQuery(Request $request)
    {
        $query = Purchase::with(['supplier', 'user']);

        if ($request->filled('start_date')) {
            $query->whereDate('purchase_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('purchase_date', '<=', $request->end_date);
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $query;
    }
}

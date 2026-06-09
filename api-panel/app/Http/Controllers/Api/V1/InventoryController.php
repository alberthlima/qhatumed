<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\InventoryAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class InventoryController extends Controller
{
    /**
     * Get active batches (FEFO control).
     */
    public function batches(Request $request)
    {
        $status = $request->status ?? 'TODOS';
        $today = now()->toDateString();
        $soonDate = now()->addDays(60)->toDateString();

        $query = PurchaseItem::with(['product.laboratory', 'product.pharmaceuticalForm', 'purchase.supplier'])
            ->where('current_stock', '>', 0);

        // Filter by search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('batch', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by expiration status
        if ($status === 'VENCIDOS') {
            $query->whereNotNull('expiration_date')
                  ->where('expiration_date', '<', $today);
        } elseif ($status === 'POR_VENCER') {
            $query->whereNotNull('expiration_date')
                  ->whereBetween('expiration_date', [$today, $soonDate]);
        } elseif ($status === 'VIGENTES') {
            $query->where(function ($q) use ($soonDate) {
                $q->whereNull('expiration_date')
                  ->orWhere('expiration_date', '>', $soonDate);
            });
        }

        // FEFO order: soonest to expire first
        $query->orderBy('expiration_date', 'asc');

        $batches = $query->paginate($request->per_page ?? 15);

        // Add dynamically calculated days remaining
        $batches->getCollection()->transform(function ($batch) use ($today) {
            if ($batch->expiration_date) {
                $diff = strtotime($batch->expiration_date) - strtotime($today);
                $batch->days_remaining = (int) round($diff / (60 * 60 * 24));
            } else {
                $batch->days_remaining = null;
            }
            return $batch;
        });

        return response()->json($batches);
    }

    /**
     * Get inventory valuation summary (PEPS).
     */
    public function valuation(Request $request)
    {
        // Calculate global summary stats first
        $stats = DB::table('purchase_items')
            ->join('products', 'purchase_items.product_id', '=', 'products.id')
            ->where('purchase_items.current_stock', '>', 0)
            ->select(
                DB::raw('SUM(purchase_items.current_stock * purchase_items.unit_cost) as total_cost'),
                DB::raw('SUM(purchase_items.current_stock * COALESCE(purchase_items.suggested_sale_price, products.sale_price)) as total_sale')
            )
            ->first();

        $totalCost = $stats->total_cost ?? 0;
        $totalSale = $stats->total_sale ?? 0;
        $totalMargin = $totalSale - $totalCost;
        $marginPercent = $totalCost > 0 ? ($totalMargin / $totalCost) * 100 : 0;

        // Fetch products with their active batches for detailed grid
        $query = Product::with(['laboratory', 'purchaseItems' => function ($q) {
            $q->where('current_stock', '>', 0);
        }])
        ->whereHas('purchaseItems', function ($q) {
            $q->where('current_stock', '>', 0);
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Paginate results
        $products = $query->orderBy('name', 'asc')->paginate($request->per_page ?? 15);

        // Transform results to calculate PEPS valuation per product line
        $products->getCollection()->transform(function ($product) {
            $stock = $product->purchaseItems->sum('current_stock');
            $costValuation = $product->purchaseItems->sum(function ($item) {
                return $item->current_stock * $item->unit_cost;
            });
            $saleValuation = $product->purchaseItems->sum(function ($item) use ($product) {
                $price = $item->suggested_sale_price ?: $product->sale_price;
                return $item->current_stock * $price;
            });

            $marginVal = $saleValuation - $costValuation;
            $marginPct = $costValuation > 0 ? ($marginVal / $costValuation) * 100 : 0;

            return [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'laboratory' => $product->laboratory ? $product->laboratory->name : 'Genérico',
                'stock' => $stock,
                'average_cost' => $product->average_cost,
                'sale_price' => $product->sale_price,
                'cost_valuation' => round($costValuation, 2),
                'sale_valuation' => round($saleValuation, 2),
                'margin_value' => round($marginVal, 2),
                'margin_percent' => round($marginPct, 2)
            ];
        });

        return response()->json([
            'summary' => [
                'total_cost' => round($totalCost, 2),
                'total_sale' => round($totalSale, 2),
                'total_margin' => round($totalMargin, 2),
                'margin_percent' => round($marginPercent, 2)
            ],
            'products' => $products
        ]);
    }

    /**
     * Get stock adjustments history.
     */
    public function adjustments(Request $request)
    {
        $adjustments = InventoryAdjustment::with(['user', 'product', 'purchaseItem'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($adjustments);
    }

    /**
     * Store a new stock adjustment.
     */
    public function storeAdjustment(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'purchase_item_id' => 'required|exists:purchase_items,id',
            'type' => 'required|in:ADD,SUBTRACT',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|in:AUDITORIA_FISICA,VENCIMIENTO,DANADO,PERDIDA,OTROS',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $adjustment = DB::transaction(function () use ($request) {
                $batch = PurchaseItem::lockForUpdate()->findOrFail($request->purchase_item_id);
                $product = Product::lockForUpdate()->findOrFail($request->product_id);
                $qty = $request->quantity;

                if ($batch->product_id !== $product->id) {
                    throw new \Exception("El lote seleccionado no pertenece a este producto.");
                }

                if ($request->type === 'SUBTRACT') {
                    if ($batch->current_stock < $qty) {
                        throw new \Exception("Stock insuficiente en el lote seleccionado. Disponible: {$batch->current_stock}");
                    }
                    $batch->decrement('current_stock', $qty);
                    $product->decrement('stock', $qty);
                } else {
                    $batch->increment('current_stock', $qty);
                    $product->increment('stock', $qty);
                }

                return InventoryAdjustment::create([
                    'user_id' => auth()->id() ?? 1,
                    'product_id' => $product->id,
                    'purchase_item_id' => $batch->id,
                    'type' => $request->type,
                    'quantity' => $qty,
                    'reason' => $request->reason,
                    'notes' => $request->notes
                ]);
            });

            return response()->json([
                'message' => 'Ajuste de inventario procesado correctamente.',
                'data' => $adjustment->load(['user', 'product', 'purchaseItem'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Generate valuation PDF report.
     */
    public function downloadValuationPdf()
    {
        try {
            $config = $this->getSiatConfig();

            // Fetch valuation data
            $products = Product::with(['laboratory', 'purchaseItems' => function ($q) {
                $q->where('current_stock', '>', 0);
            }])
            ->whereHas('purchaseItems', function ($q) {
                $q->where('current_stock', '>', 0);
            })
            ->orderBy('name', 'asc')
            ->get();

            $productsData = $products->map(function ($product) {
                $stock = $product->purchaseItems->sum('current_stock');
                $costValuation = $product->purchaseItems->sum(function ($item) {
                    return $item->current_stock * $item->unit_cost;
                });
                $saleValuation = $product->purchaseItems->sum(function ($item) use ($product) {
                    $price = $item->suggested_sale_price ?: $product->sale_price;
                    return $item->current_stock * $price;
                });

                return [
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'laboratory' => $product->laboratory ? $product->laboratory->name : 'Genérico',
                    'stock' => $stock,
                    'average_cost' => $product->average_cost,
                    'sale_price' => $product->sale_price,
                    'cost_valuation' => $costValuation,
                    'sale_valuation' => $saleValuation
                ];
            });

            $totalCost = $productsData->sum('cost_valuation');
            $totalSale = $productsData->sum('sale_valuation');

            $pdf = Pdf::loadView('pdf.inventory_valuation', [
                'products' => $productsData,
                'totalCost' => $totalCost,
                'totalSale' => $totalSale,
                'config' => $config
            ]);

            $pdf->setPaper('letter', 'portrait');
            return response($pdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="Inventario_Valorizado.pdf"');

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generate batches and expirations PDF report.
     */
    public function downloadBatchesPdf(Request $request)
    {
        try {
            $config = $this->getSiatConfig();
            $status = $request->status ?? 'TODOS';
            $today = now()->toDateString();
            $soonDate = now()->addDays(60)->toDateString();

            $query = PurchaseItem::with(['product.laboratory'])
                ->where('current_stock', '>', 0);

            if ($status === 'VENCIDOS') {
                $query->whereNotNull('expiration_date')->where('expiration_date', '<', $today);
            } elseif ($status === 'POR_VENCER') {
                $query->whereNotNull('expiration_date')->whereBetween('expiration_date', [$today, $soonDate]);
            } elseif ($status === 'VIGENTES') {
                $query->where(function ($q) use ($soonDate) {
                    $q->whereNull('expiration_date')->orWhere('expiration_date', '>', $soonDate);
                });
            }

            $batches = $query->orderBy('expiration_date', 'asc')->get();

            $batches->transform(function ($batch) use ($today) {
                if ($batch->expiration_date) {
                    $diff = strtotime($batch->expiration_date) - strtotime($today);
                    $batch->days_remaining = (int) round($diff / (60 * 60 * 24));
                } else {
                    $batch->days_remaining = null;
                }
                return $batch;
            });

            $pdf = Pdf::loadView('pdf.inventory_batches', [
                'batches' => $batches,
                'status' => $status,
                'today' => $today,
                'config' => $config
            ]);

            $pdf->setPaper('letter', 'portrait');
            return response($pdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="Control_Lotes_Vencimientos.pdf"');

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar PDF: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve SIAT Config/Company Details.
     */
    private function getSiatConfig(): array
    {
        $config = config('siat') ?? [];
        try {
            $setting = \App\Models\CompanySetting::first();
            if ($setting) {
                if ($setting->legal_name) $config['razon_social'] = $setting->legal_name;
                if ($setting->nit) $config['nit'] = $setting->nit;
                if ($setting->siat_branch_code !== null) $config['sucursal'] = $setting->siat_branch_code;
                if ($setting->siat_pos_code !== null) $config['punto_venta'] = $setting->siat_pos_code;
            }
        } catch (\Exception $e) {}

        if (empty($config['razon_social'])) $config['razon_social'] = 'FARMACIA MISALUD';
        if (empty($config['nit'])) $config['nit'] = '6789406016';
        if (empty($config['direccion'])) $config['direccion'] = 'Av. Principal #123';
        if (empty($config['municipio'])) $config['municipio'] = 'Santa Cruz';

        return $config;
    }
}

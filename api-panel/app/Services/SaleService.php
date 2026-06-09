<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Product;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use App\Services\SiatInvoiceService;

class SaleService
{
    protected SiatInvoiceService $siatService;

    public function __construct(SiatInvoiceService $siatService)
    {
        $this->siatService = $siatService;
    }

    /**
     * Store a new sale and deduct stock strictly via FEFO.
     */
    public function createSale(array $data, int $userId)
    {
        return DB::transaction(function () use ($data, $userId) {
            
            // 1. Create Sale Header
            $sale = Sale::create([
                'customer_id'    => $data['customer_id'] ?? null,
                'user_id'        => $userId,
                'sale_date'      => $data['sale_date'] ?? now(),
                'business_name'  => $data['business_name'] ?? 'S/N',
                'nit_ci'         => $data['nit_ci'] ?? '0',
                
                // SIAT Setup
                'invoice_number'      => null, 
                'cuf'                 => null,
                'cufd'                => null,
                'payment_method_code' => $data['payment_method_code'] ?? 1,
                'document_type_code'  => $data['document_type_code'] ?? 1,
                'is_invoice'          => $data['is_invoice'] ?? true,
                
                'total_amount'   => 0, // Calculated below
                'cash_tendered'  => $data['cash_tendered'] ?? 0,
                'change'         => $data['change'] ?? 0,
                'status'         => 'PENDING',
            ]);

            $totalAmount = 0;

            // 2. Process Items (FEFO Logic)
            foreach ($data['items'] as $itemData) {
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);
                $quantityNeeded = $itemData['quantity'];
                
                if ($product->stock < $quantityNeeded) {
                    throw new \Exception("Stock insuficiente para el producto: {$product->name}. Stock actual: {$product->stock}");
                }

                $unitPrice = $itemData['unit_price'] ?? $product->sale_price;
                $discount = $itemData['discount'] ?? 0;
                
                $itemSubtotal = ($quantityNeeded * $unitPrice) - $discount;
                $totalAmount += $itemSubtotal;

                // Snapshot the average cost at the time of sale for financial reports
                $costAtSale = $product->average_cost;

                // Execute FEFO Physical Deduction
                $this->deductFefoStock($sale->id, $product, $quantityNeeded, $unitPrice, $discount, $costAtSale);

                // Update general product stock
                $product->decrement('stock', $quantityNeeded);
            }

            // Update Header Total
            $sale->update(['total_amount' => $totalAmount]);

            // 3. SIAT FACTURACIÓN (Si está habilitado)
            if ($sale->is_invoice) {
                $this->siatService->prepareAndEmit($sale);
            } else {
                $sale->update(['status' => 'COMPLETED']);
            }

            // 4. Registrar Movimiento de Caja (si hay turno abierto)
            $openSession = \App\Models\CashRegisterSession::where('user_id', $userId)
                ->where('status', 'OPEN')
                ->first();

            if ($openSession) {
                $methodMap = [
                    1 => 'EFECTIVO',
                    2 => 'TARJETA',
                    7 => 'QR'
                ];
                $methodStr = $methodMap[$sale->payment_method_code] ?? 'EFECTIVO';

                \App\Models\CashMovement::create([
                    'cash_register_session_id' => $openSession->id,
                    'user_id'                  => $userId,
                    'type'                     => 'IN',
                    'amount'                   => $sale->total_amount,
                    'payment_method'           => $methodStr,
                    'description'              => "Venta #" . ($sale->invoice_number ?: $sale->id),
                    'reference_id'             => $sale->id,
                    'reference_type'           => \App\Models\Sale::class
                ]);
            }

            return $sale->load(['items', 'customer']);
        });
    }

    /**
     * Logic to deduct stock using First-Expired-First-Out (FEFO)
     */
    private function deductFefoStock($saleId, $product, $quantityNeeded, $unitPrice, $discount, $costAtSale)
    {
        $remainingQuantity = $quantityNeeded;

        // Get available batches ordered by expiration date (FEFO)
        $batches = $product->activeBatches()->lockForUpdate()->get();

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $deductAmount = min($batch->current_stock, $remainingQuantity);
            
            // Deduct from the physical batch
            $batch->decrement('current_stock', $deductAmount);
            
            // Pro-rate discount if multiple batches were used for the same product line
            $proRatedDiscount = ($deductAmount / $quantityNeeded) * $discount;
            $proRatedSubtotal = ($deductAmount * $unitPrice) - $proRatedDiscount;

            // Register specific Sale Item tied to this physical batch
            SaleItem::create([
                'sale_id'              => $saleId,
                'product_id'           => $product->id,
                'purchase_item_id'     => $batch->id,
                'quantity'             => $deductAmount,
                'unit_price'           => $unitPrice,
                'discount'             => $proRatedDiscount,
                'subtotal'             => $proRatedSubtotal,
                'average_cost_at_sale' => $costAtSale, // CPP reference
                'batch_cost_at_sale'   => $batch->unit_cost // Exact batch cost reference
            ]);

            $remainingQuantity -= $deductAmount;
        }

        // If there's still quantity remaining but no batches, it means we had ghost stock.
        // For emergency selling, we attach it to a ghost batch (null purchase_item_id).
        if ($remainingQuantity > 0) {
            $proRatedDiscount = ($remainingQuantity / $quantityNeeded) * $discount;
            $proRatedSubtotal = ($remainingQuantity * $unitPrice) - $proRatedDiscount;

            SaleItem::create([
                'sale_id'              => $saleId,
                'product_id'           => $product->id,
                'purchase_item_id'     => null, // Ghost Stock
                'quantity'             => $remainingQuantity,
                'unit_price'           => $unitPrice,
                'discount'             => $proRatedDiscount,
                'subtotal'             => $proRatedSubtotal,
                'average_cost_at_sale' => $costAtSale,
                'batch_cost_at_sale'   => null
            ]);
        }
    }

    /**
     * Annul a sale and return stock to respective batches, and annul invoice in SIAT if applicable.
     */
    public function annulSale(Sale $sale, int $motivoAnulacion = 1)
    {
        if ($sale->status === 'ANNULLED') {
            throw new \Exception("Venta ya se encuentra anulada.");
        }

        // 1. Si es factura electrónica, anular en SIAT primero.
        if ($sale->is_invoice && !empty($sale->cuf)) {
            $this->siatService->annulInvoice($sale, $motivoAnulacion);
        }

        return DB::transaction(function () use ($sale) {
            foreach ($sale->items as $item) {
                $product = $item->product;
                
                // Return stock to the general catalog
                $product->increment('stock', $item->quantity);
                
                // Return physical stock to the specific batch if it was tracked
                if ($item->purchase_item_id) {
                    $item->purchaseItem()->increment('current_stock', $item->quantity);
                }
            }

            $sale->update(['status' => 'ANNULLED']);
            
            // Delete associated cash movement
            \App\Models\CashMovement::where('reference_id', $sale->id)
                ->where('reference_type', \App\Models\Sale::class)
                ->delete();
            
            return $sale;
        });
    }
}

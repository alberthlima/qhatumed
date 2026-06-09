<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\Product;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    /**
     * Store a new purchase and update product average costs (CPP).
     */
    public function createPurchase(array $data, int $userId)
    {
        return DB::transaction(function () use ($data, $userId) {
            // Create Purchase Record
            $purchase = Purchase::create([
                'supplier_id'    => $data['supplier_id'] ?? null,
                'user_id'        => $userId,
                'reason'         => $data['reason'] ?? 'COMPRA',
                'purchase_date'  => $data['purchase_date'] ?? now(),
                'invoice_number' => $data['invoice_number'] ?? null,
                'total_amount'   => 0, // Will be calculated below
                'status'         => 'COMPLETED',
            ]);

            $totalAmount = 0;

            // Process Items
            foreach ($data['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                
                $quantity = $itemData['quantity'];
                $unitCost = $itemData['unit_cost'];
                
                $subtotal = $quantity * $unitCost;
                $totalAmount += $subtotal;

                // 1. Create Purchase Item (The physical Batch/Lote)
                PurchaseItem::create([
                    'purchase_id'          => $purchase->id,
                    'product_id'           => $product->id,
                    'batch'                => $itemData['batch'] ?? null,
                    'expiration_date'      => $itemData['expiration_date'] ?? null,
                    'quantity'             => $quantity,
                    'current_stock'        => $quantity, // Initially, stock equals purchased quantity
                    'unit_cost'            => $unitCost,
                    'suggested_sale_price' => $itemData['sale_price'] ?? $itemData['suggested_sale_price'] ?? null,
                ]);

                // 2. Calculate New Average Cost (CPP)
                // CPP = (CurrentTotalValue + NewTotalValue) / (CurrentStock + NewQuantity)
                $currentStock = $product->stock;
                $currentAvgCost = $product->average_cost;

                $currentTotalValue = $currentStock * $currentAvgCost;
                $newTotalValue = $quantity * $unitCost;
                $newStock = $currentStock + $quantity;

                $newAvgCost = ($newStock > 0) ? ($currentTotalValue + $newTotalValue) / $newStock : $unitCost;

                // 3. Update Product Stock and Cost
                // Only update sale_price if it was explicitly provided to change it catalog-wide
                $productUpdateData = [
                    'stock' => $newStock,
                    'average_cost' => round($newAvgCost, 2),
                ];
                
                if (isset($itemData['sale_price']) && $itemData['sale_price'] > 0) {
                    $productUpdateData['sale_price'] = $itemData['sale_price'];
                }
                
                $product->update($productUpdateData);
            }

            // Update Total Amount for the Purchase Document
            $purchase->update(['total_amount' => $totalAmount]);

            return $purchase->load('items');
        });
    }

    /**
     * Annul a purchase (Revert stock and recalculate)
     */
    public function annulPurchase(Purchase $purchase)
    {
        if ($purchase->status === 'ANNULLED') {
            throw new \Exception("Purchase is already annulled.");
        }

        return DB::transaction(function () use ($purchase) {
            foreach ($purchase->items as $item) {
                // If any of the stock was already sold, we can't reliably annul without complex logic or forcing negative stock
                if ($item->current_stock < $item->quantity) {
                    throw new \Exception("Cannot annul purchase. Stock from batch {$item->batch} has already been sold.");
                }

                $product = $item->product;

                // Revert CPP calculation if possible, though strict accounting prefers adjustments rather than annulments if average changed.
                // For a simple system, we just subtract the stock.
                $newStock = $product->stock - $item->quantity;
                $product->update(['stock' => max($newStock, 0)]);
                
                // We set internal batch stock to 0 so it's not sold anymore
                $item->update(['current_stock' => 0]); 
            }

            $purchase->update(['status' => 'ANNULLED']);
            
            return $purchase;
        });
    }
}

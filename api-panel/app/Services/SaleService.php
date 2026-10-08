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
            
            // Calcular fecha y hora exacta
            $saleDateTime = now('America/La_Paz');
            if (!empty($data['sale_date'])) {
                if (strlen($data['sale_date']) <= 10) {
                    $saleDateTime = \Carbon\Carbon::parse($data['sale_date'], 'America/La_Paz')
                        ->setTimeFromTimeString(now('America/La_Paz')->format('H:i:s'));
                } else {
                    $saleDateTime = \Carbon\Carbon::parse($data['sale_date'])->timezone('America/La_Paz');
                }
            }

            // 1. Create Sale Header
            $sale = Sale::create([
                'customer_id'    => $data['customer_id'] ?? null,
                'user_id'        => $userId,
                'sale_date'      => $saleDateTime,
                'business_name'  => $data['business_name'] ?? 'S/N',
                'nit_ci'         => $data['nit_ci'] ?? '0',
                
                // SIAT Setup
                'invoice_number'      => !empty($data['invoice_number']) ? (int) $data['invoice_number'] : null, 
                'cuf'                 => null,
                'cufd'                => null,
                'payment_method_code' => (int) ($data['payment_method_code'] ?? 1),
                'card_number'         => $data['card_number'] ?? null,
                'card_amount'         => round((float) ($data['card_amount'] ?? 0), 2),
                'gift_card_amount'    => round((float) ($data['gift_card_amount'] ?? 0), 2),
                'gift_card_code'      => $data['gift_card_code'] ?? null,
                'document_type_code'  => in_array(trim((string) ($data['nit_ci'] ?? '')), ['99001', '99002', '99003'], true) ? 5 : ($data['document_type_code'] ?? 1),
                'exception_code'      => isset($data['exception_code']) && (int) $data['exception_code'] > 0 ? (int) $data['exception_code'] : null,
                'is_invoice'          => $data['is_invoice'] ?? true,
                'codigo_emision'      => (int) ($data['codigo_emision'] ?? (!empty($data['is_offline']) ? 2 : 1)),
                'cafc'                => !empty($data['cafc']) ? trim((string) $data['cafc']) : null,
                'estado_siat'         => (!empty($data['is_offline']) || (int) ($data['codigo_emision'] ?? 1) === 2) ? 'OFFLINE_PENDIENTE' : 'ONLINE_VALIDADA',
                
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

                $unitPrice = round((float) ($itemData['unit_price'] ?? $product->sale_price), 2);
                $discount = round((float) ($itemData['discount'] ?? 0), 2);
                $grossAmount = round($quantityNeeded * $unitPrice, 2);

                // Norma SIN: subTotal = (cantidad x precioUnitario) - montoDescuento.
                // En el XSD oficial del SIN (Factura Compra Venta), subTotal tiene la restricción minExclusive="0.0"
                // (debe ser estrictamente mayor a 0). Si se aplica un descuento del 100%, el tope legal es (importe bruto - 0.01 Bs).
                if ($discount >= $grossAmount && $grossAmount > 0.01) {
                    $discount = round($grossAmount - 0.01, 2);
                } elseif ($discount > $grossAmount) {
                    throw new \Exception("Descuento inválido en {$product->name}: el descuento (Bs. {$discount}) no puede superar el importe del ítem (Bs. {$grossAmount}).");
                }
                
                $itemSubtotal = round($grossAmount - $discount, 2);
                $totalAmount += $itemSubtotal;

                // Snapshot the average cost at the time of sale for financial reports
                $costAtSale = $product->average_cost;

                // Execute FEFO Physical Deduction
                $this->deductFefoStock($sale->id, $product, $quantityNeeded, $unitPrice, $discount, $costAtSale);

                // Update general product stock
                $product->decrement('stock', $quantityNeeded);
            }

            // Norma SIN: montoTotal = Σ subTotal - descuentoAdicional
            $additionalDiscount = round((float) ($data['discount_amount'] ?? 0), 2);
            if ($additionalDiscount > $totalAmount) {
                throw new \Exception("Descuento adicional inválido: (Bs. {$additionalDiscount}) no puede superar la suma de subtotales (Bs. " . number_format($totalAmount, 2) . ").");
            }
            $totalAmount = round($totalAmount - $additionalDiscount, 2);

            // Validación Normativa SIN: No se permite emitir facturas con importe cero o negativo,
            // salvo en pagos totales cubiertos por Gift Card (Código 27)
            if ($totalAmount <= 0 && (int) $sale->payment_method_code !== 27) {
                throw new \Exception("Monto inválido: El monto total de la venta debe ser mayor a 0. No se permite registrar ni emitir facturas con importe cero o negativo, salvo pagos cubiertos por Gift Card.");
            }

            // Si es combinado, validar coherencia de desglose efectivo + tarjeta
            if ((int) $sale->payment_method_code === 10) {
                $cardPortion = round((float) ($sale->card_amount ?? 0), 2);
                if ($cardPortion <= 0 || $cardPortion >= $totalAmount) {
                    throw new \Exception("Pago combinado inválido: El monto en tarjeta debe ser mayor a 0 y menor al total de la venta (Bs. " . number_format($totalAmount, 2) . ").");
                }
            }

            // Update Header Total
            $sale->update([
                'total_amount'        => $totalAmount,
                'additional_discount' => $additionalDiscount,
            ]);

            // 3. SIAT FACTURACIÓN (Si está habilitado)
            if ($sale->is_invoice) {
                $isOfflineRequested = ((int) $sale->codigo_emision === 2) || !empty($data['is_offline']);
                if ($isOfflineRequested) {
                    $this->siatService->prepareAndEmit($sale, forceOffline: true);
                } else {
                    try {
                        $this->siatService->prepareAndEmit($sale, forceOffline: false);
                    } catch (\Throwable $e) {
                        // Respaldo de Contingencia Automática (RND 102100000011 Art. 29-33):
                        // Ante corte de internet o indisponibilidad del servicio web del SIN,
                        // no interrumpir la venta en mostrador; emitir en contingencia fuera de línea.
                        \Illuminate\Support\Facades\Log::warning("Falla al conectar con SIAT en línea para Venta #{$sale->id}. Activando emisión en contingencia (Fuera de Línea): " . $e->getMessage());
                        $this->siatService->prepareAndEmit($sale, forceOffline: true);
                    }
                }
            } else {
                $sale->update(['status' => 'COMPLETED']);
            }

            // 4. Registrar Movimiento de Caja (si hay turno abierto)
            $openSession = \App\Models\CashRegisterSession::where('user_id', $userId)
                ->where('status', 'OPEN')
                ->first();

            if ($openSession) {
                $saleNum = $sale->invoice_number ?: $sale->id;

                if ((int) $sale->payment_method_code === 10) {
                    // Método Combinado: Dividir exactamente en movimiento de efectivo y movimiento de tarjeta
                    $cardPart = round((float) ($sale->card_amount ?? 0), 2);
                    $cashPart = round(max(0, $sale->total_amount - $cardPart), 2);

                    if ($cashPart > 0) {
                        \App\Models\CashMovement::create([
                            'cash_register_session_id' => $openSession->id,
                            'user_id'                  => $userId,
                            'type'                     => 'IN',
                            'amount'                   => $cashPart,
                            'payment_method'           => 'EFECTIVO',
                            'description'              => "Venta #{$saleNum} (Efectivo parte combinada)",
                            'reference_id'             => $sale->id,
                            'reference_type'           => \App\Models\Sale::class
                        ]);
                    }

                    if ($cardPart > 0) {
                        \App\Models\CashMovement::create([
                            'cash_register_session_id' => $openSession->id,
                            'user_id'                  => $userId,
                            'type'                     => 'IN',
                            'amount'                   => $cardPart,
                            'payment_method'           => 'TARJETA',
                            'description'              => "Venta #{$saleNum} (Tarjeta parte combinada)",
                            'reference_id'             => $sale->id,
                            'reference_type'           => \App\Models\Sale::class
                        ]);
                    }
                } else {
                    $methodMap = [
                        1  => 'EFECTIVO',
                        2  => 'TARJETA',
                        7  => 'QR',
                        27 => 'GIFT_CARD',
                        35 => 'PAGO_POSTERIOR',
                    ];
                    $methodStr = $methodMap[$sale->payment_method_code] ?? 'EFECTIVO';

                    // Ventas a crédito o pago posterior no ingresan dinero físico a la caja de turno
                    if ((int) $sale->payment_method_code !== 35) {
                        \App\Models\CashMovement::create([
                            'cash_register_session_id' => $openSession->id,
                            'user_id'                  => $userId,
                            'type'                     => 'IN',
                            'amount'                   => $sale->total_amount,
                            'payment_method'           => $methodStr,
                            'description'              => "Venta #{$saleNum}" . ($sale->payment_method_code == 27 ? " (Gift Card)" : ""),
                            'reference_id'             => $sale->id,
                            'reference_type'           => \App\Models\Sale::class
                        ]);
                    }
                }
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
        $chunks = []; // [quantity, purchase_item_id|null, batch_cost|null]

        // Get available batches ordered by expiration date (FEFO)
        $batches = $product->activeBatches()->lockForUpdate()->get();

        foreach ($batches as $batch) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $deductAmount = min($batch->current_stock, $remainingQuantity);
            
            // Deduct from the physical batch
            $batch->decrement('current_stock', $deductAmount);

            $chunks[] = [$deductAmount, $batch->id, $batch->unit_cost];
            $remainingQuantity -= $deductAmount;
        }

        // If there's still quantity remaining but no batches, it means we had ghost stock.
        // For emergency selling, we attach it to a ghost batch (null purchase_item_id).
        if ($remainingQuantity > 0) {
            $chunks[] = [$remainingQuantity, null, null];
        }

        // Pro-rate the item discount across the batch lines. Each line is rounded to cents and
        // the last line absorbs the remainder, so that sum(line discounts) == item discount.
        // (SIAT validates subTotal = cantidad x precioUnitario - montoDescuento per line.)
        $discountLeft = round((float) $discount, 2);
        $lastIndex = count($chunks) - 1;

        foreach ($chunks as $i => [$qty, $purchaseItemId, $batchCost]) {
            $lineDiscount = ($i === $lastIndex)
                ? $discountLeft
                : min($discountLeft, round(($qty / $quantityNeeded) * $discount, 2));
            $discountLeft = round($discountLeft - $lineDiscount, 2);

            SaleItem::create([
                'sale_id'              => $saleId,
                'product_id'           => $product->id,
                'purchase_item_id'     => $purchaseItemId,
                'quantity'             => $qty,
                'unit_price'           => $unitPrice,
                'discount'             => $lineDiscount,
                'subtotal'             => round(($qty * $unitPrice) - $lineDiscount, 2),
                'average_cost_at_sale' => $costAtSale, // CPP reference
                'batch_cost_at_sale'   => $batchCost,  // Exact batch cost reference (null for ghost stock)
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

            $updateData = ['status' => 'ANNULLED'];
            if ($sale->is_invoice) {
                $updateData['estado_siat'] = 'ANULADA';
            }
            $sale->update($updateData);
            
            // Delete associated cash movement
            \App\Models\CashMovement::where('reference_id', $sale->id)
                ->where('reference_type', \App\Models\Sale::class)
                ->delete();
            
            return $sale;
        });
    }

    /**
     * Revierte la anulación de una venta/factura:
     * - Revierte la anulación en el SIAT ante el SIN (reversionAnulacionFactura - RND 102100000011)
     * - Re-descuenta el stock físico de productos y lotes
     * - Restaura el movimiento de caja en la sesión abierta del usuario
     * - Cambia el estado de la venta nuevamente a COMPLETED
     */
    public function revertSale(Sale $sale)
    {
        if ($sale->status !== 'ANNULLED') {
            throw new \Exception("Solo se puede revertir una venta que se encuentre anulada.");
        }

        // Validación de Plazo de Reversión (RND 102100000011 Art. 45)
        if ($sale->sale_date) {
            $fechaEmision = \Carbon\Carbon::parse($sale->sale_date)->timezone('America/La_Paz');
            $limiteReversion = $fechaEmision->copy()->addMonth()->startOfMonth()->addDays(9)->endOfDay();
            if (\Carbon\Carbon::now('America/La_Paz')->greaterThan($limiteReversion)) {
                $fEmit = $fechaEmision->format('d/m/Y');
                $fLim = $limiteReversion->format('d/m/Y');
                throw new \Exception("Plazo de reversión vencido. Según normativa RND 102100000011 Art. 45, la factura fue emitida el {$fEmit} y el plazo máximo venció el {$fLim}.");
            }
        }

        // 1. Si es factura con CUF, revertir en SIAT primero
        if ($sale->is_invoice && !empty($sale->cuf)) {
            $this->siatService->revertInvoice($sale);
        }

        return DB::transaction(function () use ($sale) {
            // 2. Verificar y volver a descontar el stock
            foreach ($sale->items as $item) {
                $product = \App\Models\Product::lockForUpdate()->findOrFail($item->product_id);
                if ($product->stock < $item->quantity) {
                    throw new \Exception("Stock insuficiente para revertir la venta del producto: {$product->name}. Stock actual: {$product->stock}");
                }

                $product->decrement('stock', $item->quantity);

                if ($item->purchase_item_id) {
                    $purchaseItem = $item->purchaseItem()->lockForUpdate()->first();
                    if ($purchaseItem) {
                        $purchaseItem->decrement('current_stock', $item->quantity);
                    }
                }
            }

            $updateData = ['status' => 'COMPLETED'];
            if ($sale->is_invoice) {
                $updateData['estado_siat'] = ((int)$sale->codigo_emision === 2) ? 'OFFLINE_PENDIENTE' : 'ONLINE_VALIDADA';
            }
            $sale->update($updateData);

            // 3. Restaurar movimiento de caja si hay turno abierto
            $openSession = \App\Models\CashRegisterSession::where('user_id', $sale->user_id)
                ->where('status', 'OPEN')
                ->first();

            if ($openSession) {
                $saleNum = $sale->invoice_number ?: $sale->id;
                \App\Models\CashMovement::create([
                    'cash_register_session_id' => $openSession->id,
                    'user_id'                  => $sale->user_id,
                    'type'                     => 'IN',
                    'amount'                   => $sale->total_amount,
                    'payment_method'           => (int)$sale->payment_method_code === 2 ? 'TARJETA' : 'EFECTIVO',
                    'description'              => "Venta #{$saleNum} (Reversión de Anulación)",
                    'reference_id'             => $sale->id,
                    'reference_type'           => \App\Models\Sale::class
                ]);
            }

            return $sale;
        });
    }
}

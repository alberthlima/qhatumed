<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class NotificationController extends Controller
{
    /**
     * Retorna las alertas consolidadas de stock y vencimiento de lotes.
     */
    public function stockAlerts(Request $request)
    {
        $userId = auth()->id() ?? 1;
        $now = Carbon::now('America/La_Paz');

        // Obtener IDs de notificaciones ya vistas o descartadas por este usuario
        $seenIds = Cache::get("user_seen_notifications_{$userId}", []);
        if (!is_array($seenIds)) {
            $seenIds = [];
        }

        $notifications = [];

        // 1. Lotes Vencidos con stock disponible (Severidad: ERROR / Crítico)
        $expiredBatches = DB::table('purchase_items')
            ->join('products', 'purchase_items.product_id', '=', 'products.id')
            ->where('purchase_items.current_stock', '>', 0)
            ->where('purchase_items.expiration_date', '<', $now->toDateString())
            ->select(
                'purchase_items.id as item_id',
                'purchase_items.batch',
                'purchase_items.expiration_date',
                'purchase_items.current_stock',
                'products.id as product_id',
                'products.name as product_name'
            )
            ->orderBy('purchase_items.expiration_date', 'asc')
            ->limit(20)
            ->get();

        foreach ($expiredBatches as $batch) {
            $expDate = Carbon::parse($batch->expiration_date);
            $daysPast = abs($now->diffInDays($expDate, false));
            $id = "exp-batch-{$batch->item_id}";

            $notifications[] = [
                'id'        => $id,
                'title'     => '🚨 Lote Vencido',
                'subtitle'  => "{$batch->product_name} • Lote #{$batch->batch} (Stock: {$batch->current_stock})",
                'time'      => "Venció hace {$daysPast} d",
                'isSeen'    => in_array($id, $seenIds, true),
                'color'     => 'error',
                'icon'      => 'ri-calendar-close-line',
                'type'      => 'expired_batch',
                'link'      => '/inventario/lotes',
                'meta'      => [
                    'product_id'      => $batch->product_id,
                    'batch'           => $batch->batch,
                    'current_stock'   => $batch->current_stock,
                    'expiration_date' => $batch->expiration_date,
                ],
            ];
        }

        // 2. Lotes por vencer en los próximos 30 días con stock (Severidad: WARNING / Urgente)
        $expiringBatches = DB::table('purchase_items')
            ->join('products', 'purchase_items.product_id', '=', 'products.id')
            ->where('purchase_items.current_stock', '>', 0)
            ->whereBetween('purchase_items.expiration_date', [
                $now->toDateString(),
                $now->copy()->addDays(30)->toDateString()
            ])
            ->select(
                'purchase_items.id as item_id',
                'purchase_items.batch',
                'purchase_items.expiration_date',
                'purchase_items.current_stock',
                'products.id as product_id',
                'products.name as product_name'
            )
            ->orderBy('purchase_items.expiration_date', 'asc')
            ->limit(20)
            ->get();

        foreach ($expiringBatches as $batch) {
            $expDate = Carbon::parse($batch->expiration_date);
            $daysLeft = $now->diffInDays($expDate, false);
            $daysText = $daysLeft <= 0 ? 'Vence hoy' : "Vence en {$daysLeft} días";
            $id = "expiring-batch-{$batch->item_id}";

            $notifications[] = [
                'id'        => $id,
                'title'     => '⚠️ Lote por Vencer',
                'subtitle'  => "{$batch->product_name} • Lote #{$batch->batch} ({$batch->current_stock} unids)",
                'time'      => $daysText,
                'isSeen'    => in_array($id, $seenIds, true),
                'color'     => 'warning',
                'icon'      => 'ri-time-line',
                'type'      => 'expiring_batch',
                'link'      => '/inventario/lotes',
                'meta'      => [
                    'product_id'      => $batch->product_id,
                    'batch'           => $batch->batch,
                    'current_stock'   => $batch->current_stock,
                    'expiration_date' => $batch->expiration_date,
                ],
            ];
        }

        // 3. Productos con Stock Bajo / Crítico (stock > 0 y stock <= min_stock)
        $lowStockProducts = DB::table('products')
            ->where(function ($q) {
                $q->where('status', 1)->orWhere('status', 'ACTIVE');
            })
            ->where('stock', '>', 0)
            ->whereRaw('stock <= min_stock')
            ->select('id', 'name', 'stock', 'min_stock')
            ->orderBy('stock', 'asc')
            ->limit(20)
            ->get();

        foreach ($lowStockProducts as $prod) {
            $id = "low-stock-{$prod->id}";
            $notifications[] = [
                'id'        => $id,
                'title'     => '📉 Stock Bajo',
                'subtitle'  => "{$prod->name}: quedan {$prod->stock} (Mínimo: {$prod->min_stock})",
                'time'      => "Quedan {$prod->stock}",
                'isSeen'    => in_array($id, $seenIds, true),
                'color'     => 'warning',
                'icon'      => 'ri-error-warning-line',
                'type'      => 'low_stock',
                'link'      => "/productos/editar/{$prod->id}",
                'meta'      => [
                    'product_id' => $prod->id,
                    'stock'      => $prod->stock,
                    'min_stock'  => $prod->min_stock,
                ],
            ];
        }

        // 4. Productos Agotados Relevantes (stock <= 0 que hayan tenido compras registradas)
        $outOfStockWithHistory = DB::table('products')
            ->join('purchase_items', 'products.id', '=', 'purchase_items.product_id')
            ->where(function ($q) {
                $q->where('products.status', 1)->orWhere('products.status', 'ACTIVE');
            })
            ->where('products.stock', '<=', 0)
            ->select('products.id', 'products.name', 'products.stock', 'products.min_stock')
            ->distinct()
            ->limit(15)
            ->get();

        foreach ($outOfStockWithHistory as $prod) {
            $id = "out-of-stock-{$prod->id}";
            $notifications[] = [
                'id'        => $id,
                'title'     => '❌ Producto Agotado',
                'subtitle'  => "{$prod->name} sin existencias en mostrador",
                'time'      => 'Sin stock',
                'isSeen'    => in_array($id, $seenIds, true),
                'color'     => 'error',
                'icon'      => 'ri-alarm-warning-line',
                'type'      => 'out_of_stock',
                'link'      => "/productos/editar/{$prod->id}",
                'meta'      => [
                    'product_id' => $prod->id,
                    'stock'      => $prod->stock,
                    'min_stock'  => $prod->min_stock,
                ],
            ];
        }

        // Calcular contador de no leídos
        $unreadCount = count(array_filter($notifications, fn($n) => !$n['isSeen']));

        return response()->json([
            'success'      => true,
            'unread_count' => $unreadCount,
            'summary'      => [
                'total_alerts'     => count($notifications),
                'expired_batches'  => $expiredBatches->count(),
                'expiring_batches' => $expiringBatches->count(),
                'low_stock'        => $lowStockProducts->count(),
                'out_of_stock'     => $outOfStockWithHistory->count(),
            ],
            'data'         => $notifications,
        ]);
    }

    /**
     * Marca notificaciones como leídas para el usuario autenticado.
     */
    public function markAsRead(Request $request)
    {
        $userId = auth()->id() ?? 1;
        $ids = $request->input('ids', []);
        $all = (bool) $request->input('all', false);

        $seenIds = Cache::get("user_seen_notifications_{$userId}", []);
        if (!is_array($seenIds)) {
            $seenIds = [];
        }

        if ($all) {
            // Si es all, marcamos todos los IDs enviados
            $allIds = $request->input('all_ids', []);
            $seenIds = array_unique(array_merge($seenIds, $allIds));
        } else {
            $seenIds = array_unique(array_merge($seenIds, (array)$ids));
        }

        // Guardar por 14 días
        Cache::put("user_seen_notifications_{$userId}", $seenIds, now()->addDays(14));

        return response()->json([
            'success'  => true,
            'message'  => 'Notificaciones marcadas como leídas',
            'seen_ids' => $seenIds
        ]);
    }

    /**
     * Marca notificaciones como no leídas.
     */
    public function markAsUnread(Request $request)
    {
        $userId = auth()->id() ?? 1;
        $ids = (array) $request->input('ids', []);

        $seenIds = Cache::get("user_seen_notifications_{$userId}", []);
        if (is_array($seenIds)) {
            $seenIds = array_values(array_diff($seenIds, $ids));
            Cache::put("user_seen_notifications_{$userId}", $seenIds, now()->addDays(14));
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Notificaciones marcadas como no leídas',
            'seen_ids' => $seenIds
        ]);
    }
}

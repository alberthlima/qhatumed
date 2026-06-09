<?php
 
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\User\userController;
use App\Http\Controllers\Category\categoryController;

Route::group([
    //'middleware' => 'auth:api',
    'prefix' => 'auth'
], function ($router) {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api')->name('logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api')->name('refresh');
    Route::post('/me', [AuthController::class, 'me'])->middleware('auth:api')->name('me');
});

Route::group([
    // "middleware" => ["auth:api"] // Uncomment when testing with auth token
], function($router){
    Route::resource("role", RoleController::class);

    Route::get("user/get-roles", [userController::class, 'getRoles']);
    Route::post("user/{id}", [userController::class, 'update']);
    Route::resource("user", userController::class);

    // --- API V1 (Mejores Prácticas) ---
    Route::prefix('v1')->group(function () {
        // Configuraciones de Empresa y SIAT
        Route::get('settings', [\App\Http\Controllers\CompanySettingController::class, 'show']);
        Route::post('settings', [\App\Http\Controllers\CompanySettingController::class, 'update']);
        Route::post('siat/sync', function() {
            try {
                $syncService = app(\Misalud\Siat\Services\SincronizacionService::class);
                $resultados = $syncService->sincronizarTodo();
                
                $fallidos = 0;
                foreach ($resultados as $catalogo => $res) {
                    if (!$res['ok']) {
                        $fallidos++;
                    }
                }
                
                if ($fallidos > 0) {
                    return response()->json([
                        'message' => 'Sincronización parcial con errores.',
                        'resultados' => $resultados
                    ], 500);
                }

                return response()->json([
                    'message' => 'Sincronización de catálogos finalizada correctamente.',
                    'resultados' => $resultados
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Error en la sincronización: ' . $e->getMessage()
                ], 500);
            }
        });
        Route::get('siat/catalogs', function() {
            $syncService = app(\Misalud\Siat\Services\SincronizacionService::class);
            try {
                $unidades = $syncService->obtener('unidades_medida');
            } catch (\Exception $e) {
                $unidades = [];
            }
            try {
                $productos = $syncService->obtener('productos_servicios');
            } catch (\Exception $e) {
                $productos = [];
            }
            return response()->json([
                'unidades_medida' => $unidades,
                'productos_servicios' => $productos,
            ]);
        });

        // Rutas Premium de Catálogos y Contingencias SIAT
        Route::get('siat/sync-status', [\App\Http\Controllers\Api\V1\SiatCatalogosController::class, 'status']);
        Route::get('siat/catalog/{name}', [\App\Http\Controllers\Api\V1\SiatCatalogosController::class, 'show']);
        Route::apiResource('siat/contingencias', \App\Http\Controllers\Api\V1\SiatContingenciaController::class)->only(['index', 'store']);
        Route::post('siat/contingencias/{id}/transmitir', [\App\Http\Controllers\Api\V1\SiatContingenciaController::class, 'transmitir']);

        // Core Entities
        Route::apiResource('products', \App\Http\Controllers\Api\V1\ProductController::class);
        
        // Transacciones (Ventas y Compras)
        // Usamos post para anular en lugar de delete para mantener trazabilidad histórica
        Route::apiResource('laboratories', App\Http\Controllers\System\LaboratoryController::class);
        Route::apiResource('suppliers', App\Http\Controllers\System\SupplierController::class);
        Route::apiResource('customers', App\Http\Controllers\System\CustomerController::class);
        
        // New catalog endpoints for the pharmacy structure
        Route::apiResource('pharmaceutical-forms', App\Http\Controllers\System\PharmaceuticalFormController::class);
        Route::apiResource('dispensers', App\Http\Controllers\DispenserController::class);

        Route::post('purchases/{purchase}/annul', [\App\Http\Controllers\Api\V1\PurchaseController::class, 'annul']);
        Route::get('purchases/{purchase}/pdf', [\App\Http\Controllers\Api\V1\PurchaseController::class, 'downloadPdf']);
        Route::apiResource('purchases', \App\Http\Controllers\Api\V1\PurchaseController::class)->only(['index', 'store', 'show']);
        
        Route::post('sales/{sale}/annul', [\App\Http\Controllers\Api\V1\SaleController::class, 'annul']);
        Route::get('sales/{sale}/print/thermal', [\App\Http\Controllers\Api\V1\SaleController::class, 'printThermal']);
        Route::get('sales/{sale}/print/letter', [\App\Http\Controllers\Api\V1\SaleController::class, 'printLetter']);
        Route::apiResource('sales', \App\Http\Controllers\Api\V1\SaleController::class)->only(['index', 'store', 'show']);
        Route::get('sales/{sale}/pdf', [\App\Http\Controllers\Api\V1\SaleController::class, 'downloadPdf']);
        
        // Inventarios
        Route::get('inventory/batches', [\App\Http\Controllers\Api\V1\InventoryController::class, 'batches']);
        Route::get('inventory/batches/pdf', [\App\Http\Controllers\Api\V1\InventoryController::class, 'downloadBatchesPdf']);
        Route::get('inventory/valuation', [\App\Http\Controllers\Api\V1\InventoryController::class, 'valuation']);
        Route::get('inventory/valuation/pdf', [\App\Http\Controllers\Api\V1\InventoryController::class, 'downloadValuationPdf']);
        Route::get('inventory/adjustments', [\App\Http\Controllers\Api\V1\InventoryController::class, 'adjustments']);
        Route::post('inventory/adjustments', [\App\Http\Controllers\Api\V1\InventoryController::class, 'storeAdjustment']);
        
        // Cash Register Logic
        Route::get('cash-registers', [App\Http\Controllers\Api\V1\CashRegisterController::class, 'index']);
        Route::get('cash-registers/current-session', [App\Http\Controllers\Api\V1\CashRegisterController::class, 'currentSession']);
        Route::get('cash-registers/sessions', [App\Http\Controllers\Api\V1\CashRegisterController::class, 'sessions']);
        Route::post('cash-registers/open', [App\Http\Controllers\Api\V1\CashRegisterController::class, 'openSession']);
        Route::post('cash-registers/{id}/close', [App\Http\Controllers\Api\V1\CashRegisterController::class, 'closeSession']);
        
        // Cash Movements
        Route::post('cash-movements', [App\Http\Controllers\Api\V1\CashMovementController::class, 'store']);
        Route::get('cash-movements/session/{sessionId}', [App\Http\Controllers\Api\V1\CashMovementController::class, 'index']);
        Route::get('cash-movements/historial', [App\Http\Controllers\Api\V1\CashMovementController::class, 'historial']);
    });
});
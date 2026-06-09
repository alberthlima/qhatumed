<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\CashRegisterSession;
use App\Http\Requests\StorePurchaseRequest;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseController extends Controller
{
    protected PurchaseService $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
    }

    public function index(Request $request)
    {
        $purchases = Purchase::with(['supplier', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($purchases);
    }

    public function store(StorePurchaseRequest $request)
    {
        try {
            $purchase = $this->purchaseService->createPurchase(
                $request->validated(), 
                auth()->id() ?? 1 // Fallback to 1 if testing without auth
            );

            return response()->json([
                'message' => 'Ingreso registrado correctamente.',
                'data' => $purchase
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(Purchase $purchase)
    {
        return response()->json($purchase->load(['items.product', 'supplier']));
    }
    
    public function annul(Purchase $purchase)
    {
        try {
            $this->purchaseService->annulPurchase($purchase);
            return response()->json(['message' => 'Ingreso anulado correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function downloadPdf(Purchase $purchase)
    {
        try {
            $purchase->load(['items.product', 'supplier', 'user']);
            
            // Get dynamic company details from SIAT config / Company Settings
            $config = config('siat') ?? [];
            try {
                $setting = \App\Models\CompanySetting::first();
                if ($setting) {
                    if ($setting->legal_name) $config['razon_social'] = $setting->legal_name;
                    if ($setting->nit) $config['nit'] = $setting->nit;
                    if ($setting->siat_branch_code !== null) $config['sucursal'] = $setting->siat_branch_code;
                    if ($setting->siat_pos_code !== null) $config['punto_venta'] = $setting->siat_pos_code;
                }
            } catch (\Exception $e) {
                // Ignore if not migrated
            }

            // Defaults if config is empty
            if (empty($config['razon_social'])) $config['razon_social'] = 'FARMACIA MISALUD';
            if (empty($config['nit'])) $config['nit'] = '6789406016';
            if (empty($config['direccion'])) $config['direccion'] = 'Av. Principal #123';
            if (empty($config['municipio'])) $config['municipio'] = 'Santa Cruz';

            $pdf = Pdf::loadView('pdf.purchase', [
                'purchase' => $purchase,
                'config'   => $config
            ]);

            $pdf->setPaper('letter', 'portrait');
            $pdfContent = $pdf->output();

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="Nota_Ingreso_' . $purchase->id . '.pdf"');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar PDF: ' . $e->getMessage()], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\CashRegisterSession;
use App\Http\Requests\StoreSaleRequest;
use App\Services\SaleService;
use App\Services\SiatInvoiceService;
use App\Services\SiatPdfService;
use App\Http\Resources\SaleResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    protected SaleService $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    public function index(Request $request)
    {
        $sales = Sale::with(['customer', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return SaleResource::collection($sales);
    }

    public function store(StoreSaleRequest $request)
    {
        $openSession = CashRegisterSession::where('user_id', auth()->id() ?? 1)
            ->where('status', 'OPEN')
            ->first();

        if (!$openSession) {
            return response()->json([
                'error' => 'Caja Cerrada: Debe aperturar un turno de caja antes de procesar ventas.'
            ], 403);
        }

        try {
            $sale = $this->saleService->createSale(
                $request->validated(), 
                auth()->id() ?? 1 
            );

            return new SaleResource($sale->load(['items.product', 'customer', 'user']));
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(Sale $sale)
    {
        return new SaleResource($sale->load(['items.product', 'customer', 'user']));
    }

    public function annul(Request $request, Sale $sale)
    {
        $request->validate([
            'reason_code' => 'required|integer'
        ]);

        try {
            // Si la venta tiene factura SIAT, anular primero en el SIN
            if ($sale->cuf) {
                $siat = app(SiatInvoiceService::class);
                $siat->annulInvoice($sale, $request->reason_code);
            }

            $this->saleService->annulSale($sale);
            
            return response()->json(['message' => 'Venta y Factura anuladas correctamente.']);
        } catch (\Exception $e) {
            Log::error("Error anulación: " . $e->getMessage());
            return response()->json(['error' => 'No se pudo anular: ' . $e->getMessage()], 422);
        }
    }

    public function downloadPdf(Sale $sale)
    {
        if (!$sale->cuf) {
            return response()->json(['error' => 'No hay factura fiscal para esta venta.'], 404);
        }

        try {
            $pdfService = app(SiatPdfService::class);
            $pdfContent = $pdfService->generateInvoice($sale);

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="Factura_' . $sale->invoice_number . '.pdf"');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar PDF: ' . $e->getMessage()], 500);
        }
    }

    public function printThermal(Sale $sale)
    {
        if (!$sale->cuf) {
            try {
                $pdfService = app(SiatPdfService::class);
                $pdfContent = $pdfService->generateReceipt($sale);

                return response($pdfContent)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="Recibo_' . $sale->id . '.pdf"');
            } catch (\Exception $e) {
                return response()->json(['error' => 'Error al generar Recibo: ' . $e->getMessage()], 500);
            }
        }

        try {
            $pdfService = app(SiatPdfService::class);
            $pdfContent = $pdfService->generateThermal($sale);

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="Factura_Termica_' . $sale->invoice_number . '.pdf"');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar PDF: ' . $e->getMessage()], 500);
        }
    }

    public function printLetter(Sale $sale)
    {
        if (!$sale->cuf) {
            return response()->json(['error' => 'No hay factura fiscal para esta venta.'], 404);
        }

        try {
            $pdfService = app(SiatPdfService::class);
            $pdfContent = $pdfService->generateLetter($sale);

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="Factura_Carta_' . $sale->invoice_number . '.pdf"');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al generar PDF: ' . $e->getMessage()], 500);
        }
    }
}

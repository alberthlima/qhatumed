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
            'reason_code' => 'required|integer|in:1,2,3,4'
        ]);

        try {
            // Validación de plazo normativo (RND 102100000011 Art. 45)
            if ($sale->sale_date) {
                $fechaEmision = \Carbon\Carbon::parse($sale->sale_date)->timezone('America/La_Paz');
                $limiteAnulacion = $fechaEmision->copy()->addMonth()->startOfMonth()->addDays(9)->endOfDay();
                if (\Carbon\Carbon::now('America/La_Paz')->greaterThan($limiteAnulacion)) {
                    $fEmit = $fechaEmision->format('d/m/Y');
                    $fLim = $limiteAnulacion->format('d/m/Y');
                    return response()->json([
                        'error' => "Plazo de anulación vencido. Según normativa RND 102100000011 Art. 45, la factura fue emitida el {$fEmit} y el plazo máximo de anulación venció el {$fLim}."
                    ], 422);
                }
            }

            $this->saleService->annulSale($sale, (int) ($request->reason_code ?? 1));
            
            return response()->json(['message' => 'Venta y Factura anuladas correctamente.']);
        } catch (\Exception $e) {
            Log::error("Error anulación: " . $e->getMessage());
            return response()->json(['error' => 'No se pudo anular: ' . $e->getMessage()], 422);
        }
    }

    public function revert(Request $request, Sale $sale)
    {
        try {
            $this->saleService->revertSale($sale);

            return response()->json([
                'success' => true,
                'message' => 'Anulación revertida exitosamente. La venta y factura retornaron a estado ACTIVA/VALIDADA.',
                'data'    => new SaleResource($sale->fresh()->load(['items.product', 'customer', 'user']))
            ]);
        } catch (\Exception $e) {
            Log::error("Error reversión: " . $e->getMessage());
            return response()->json(['error' => 'No se pudo revertir la anulación: ' . $e->getMessage()], 422);
        }
    }

    public function verificarSiat(Sale $sale)
    {
        if (!$sale->cuf) {
            return response()->json(['error' => 'La venta no cuenta con CUF asignado.'], 404);
        }

        try {
            $siat = app(SiatInvoiceService::class);
            $estado = $siat->verificarEstadoFactura($sale->cuf);

            return response()->json([
                'success' => true,
                'data'    => $estado
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al verificar con SIAT: ' . $e->getMessage()], 500);
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

    public function sendEmail(Request $request, Sale $sale)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        if (!$sale->cuf) {
            return response()->json(['error' => 'La venta no cuenta con factura emitida para enviar.'], 422);
        }

        try {
            $pdfService = app(SiatPdfService::class);
            $pdfContent = $pdfService->generateLetter($sale);

            // Obtener XML desde el microservicio siat-api-service
            $xmlContent = null;
            try {
                $apiUrl = rtrim(config('siat.api_url', 'http://127.0.0.1:8001/api/v1'), '/');
                $apiKey = config('siat.api_key', 'siat_live_key_9941a87bf23d8c4e01');
                $xmlRes = \Illuminate\Support\Facades\Http::withHeaders([
                    'X-API-KEY' => $apiKey,
                    'Accept'    => 'application/xml, application/json'
                ])->timeout(10)->get("{$apiUrl}/facturas/{$sale->cuf}/xml");

                if ($xmlRes->successful() && !empty($xmlRes->body())) {
                    $xmlContent = $xmlRes->body();
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("No se pudo obtener XML desde microservicio: " . $e->getMessage());
            }

            \Illuminate\Support\Facades\Mail::to($request->email)
                ->send(new \App\Mail\FacturaMailable($sale, $pdfContent, $xmlContent));

            return response()->json([
                'success' => true,
                'message' => "Factura y XML enviados exitosamente a {$request->email}"
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error enviando factura por email: " . $e->getMessage());
            return response()->json([
                'error' => "No se pudo enviar el correo: " . $e->getMessage()
            ], 500);
        }
    }
}
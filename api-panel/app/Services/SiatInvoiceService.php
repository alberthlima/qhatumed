<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Sale;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SiatInvoiceService
 * 
 * Cliente HTTP REST para el microservicio independiente SIAT (siat-api-service).
 * Gestiona la emisión y anulación de facturas electrónicas/computarizadas vía API.
 */
class SiatInvoiceService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected int $timeout;
    protected array $config;

    public function __construct()
    {
        $this->config = config('siat', []);
        $this->apiUrl = rtrim($this->config['api_url'] ?? 'http://127.0.0.1:8000/api/v1', '/');
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->timeout = (int) ($this->config['timeout'] ?? 30);

        try {
            $setting = CompanySetting::first();
            if ($setting) {
                // Si en CompanySetting se guardó una API Key con prefijo siat_
                if (!empty($setting->siat_token) && str_starts_with($setting->siat_token, 'siat_')) {
                    $this->apiKey = $setting->siat_token;
                }
                if (!empty($setting->siat_branch_code)) {
                    $this->config['sucursal'] = (int) $setting->siat_branch_code;
                }
                if (!empty($setting->siat_pos_code)) {
                    $this->config['punto_venta'] = (int) $setting->siat_pos_code;
                }
                if (!empty($setting->legal_name)) {
                    $this->config['razon_social'] = $setting->legal_name;
                }
            }
        } catch (\Throwable $e) {
            // Ignorar en caso de que la tabla o migración aún no esté lista
        }

        // Si la API key está vacía o no tiene el formato siat_, usar la configurada en .env / config
        if (empty($this->apiKey) || !str_starts_with($this->apiKey, 'siat_')) {
            $this->apiKey = $this->config['api_key'] ?? 'siat_live_key_9941a87bf23d8c4e01';
        }
    }

    /**
     * Emite una factura electrónica/computarizada ante el SIAT mediante siat-api-service.
     *
     * @param Sale $sale
     * @return Sale
     * @throws \Exception
     */
    public function prepareAndEmit(Sale $sale): Sale
    {
        $sale->loadMissing(['items.product', 'customer']);

        if (!$sale->invoice_number) {
            $sale->invoice_number = $this->getNextInvoiceNumber();
        }

        $itemsPayload = [];
        foreach ($sale->items as $item) {
            $product = $item->product;
            $itemsPayload[] = [
                'codigo_producto'         => (string) ($product->id ?? $item->product_id),
                'codigo_producto_sin'     => (int) ($product->siat_product_code ?? 45142),
                'codigo_actividad'        => (string) ($this->config['actividad_economica'] ?? '474110'),
                'descripcion'             => (string) ($product->name ?? 'Producto'),
                'cantidad'                => (float) $item->quantity,
                'precio_unitario'         => (float) $item->unit_price,
                'sub_total'               => (float) ($item->subtotal ?? ($item->quantity * $item->unit_price - ($item->discount ?? 0))),
                'codigo_unidad_medida'    => (int) ($product->siat_unit_measure_code ?? 57),
                'descuento'               => (float) ($item->discount ?? 0),
            ];
        }

        $payload = [
            'codigo_sucursal'                 => (int) ($this->config['sucursal'] ?? 0),
            'codigo_punto_venta'              => (int) ($this->config['punto_venta'] ?? 0),
            'numero_factura'                  => (int) $sale->invoice_number,
            'codigo_tipo_documento_identidad' => (int) ($sale->document_type_code ?? 1),
            'numero_documento'                => (string) ($sale->nit_ci ?? '0'),
            'complemento'                     => $sale->customer?->complement ?: null,
            'razon_social_cliente'            => (string) ($sale->business_name ?: 'CONTROL TRIBUTARIO'),
            'codigo_cliente'                  => (string) ($sale->customer_id ?? $sale->nit_ci ?? '0'),
            'correo_cliente'                  => $sale->customer?->email ?: null,
            'codigo_metodo_pago'              => (int) ($sale->payment_method_code ?? 1),
            'numero_tarjeta'                  => null,
            'monto_total'                     => (float) $sale->total_amount,
            'descuento_adicional'             => 0,
            'items'                           => $itemsPayload,
        ];

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->post("{$this->apiUrl}/facturas/emitir", $payload);

            $body = $response->body();
            if (!$response->successful() || !$response->json('success')) {
                if (strpos($body, '936') !== false || strpos($body, 'YA SE ENCUENTRA ANULADA') !== false) {
                    return true; // Ya está anulada en el SIN
                }
                $error = $response->json('error') ?? $response->body();
                $this->logError("Error emitiendo factura para Venta #{$sale->id}: {$error}");
                throw new \Exception("Error SIAT: " . $error);
            }

            $data = $response->json('data') ?? [];

            $sale->update([
                'cuf'         => $data['cuf'] ?? null,
                'cufd'        => $data['cufd'] ?? null,
                'siat_uid'    => $data['codigo_recepcion'] ?? null,
                'invoice_url' => $data['qr'] ?? null,
                'status'      => 'COMPLETED',
            ]);

            return $sale;

        } catch (\Throwable $e) {
            $this->logError("Excepción al conectar con siat-api-service para Venta #{$sale->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Anula una factura electrónica ante el SIAT.
     *
     * @param Sale $sale
     * @param int $motivo
     * @return bool
     * @throws \Exception
     */
    public function annulInvoice(Sale $sale, int $motivo = 1): bool
    {
        if (empty($sale->cuf)) {
            throw new \Exception("La venta no cuenta con CUF asignado para ser anulada.");
        }

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->post("{$this->apiUrl}/facturas/anular", [
                'cuf'           => $sale->cuf,
                'codigo_motivo' => $motivo,
            ]);

            $body = $response->body();
            if (!$response->successful() || !$response->json('success')) {
                if (strpos($body, '936') !== false || strpos($body, 'YA SE ENCUENTRA ANULADA') !== false) {
                    return true; // Ya está anulada en el SIN
                }
                $error = $response->json('error') ?? $response->body();
                $this->logError("Error anulando factura CUF {$sale->cuf}: {$error}");
                throw new \Exception("Error anulando factura en SIAT: " . $error);
            }

            return true;

        } catch (\Throwable $e) {
            $this->logError("Excepción al anular factura CUF {$sale->cuf}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calcula el correlativo siguiente de factura para la sucursal actual.
     */
    protected function getNextInvoiceNumber(): int
    {
        $lastInvoice = Sale::whereNotNull('invoice_number')
            ->orderByRaw('CAST(invoice_number AS UNSIGNED) DESC')
            ->value('invoice_number');

        return $lastInvoice ? ((int) $lastInvoice + 1) : 1;
    }

    /**
     * Registro seguro de logs.
     */
    protected function logError(string $message): void
    {
        try {
            Log::channel('siat')->error($message);
        } catch (\Throwable $e) {
            Log::error("[SIAT] " . $message);
        }
    }
}

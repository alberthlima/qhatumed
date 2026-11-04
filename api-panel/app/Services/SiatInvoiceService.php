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
                if (!empty($setting->siat_modality)) {
                    $this->config['modalidad'] = (int) $setting->siat_modality;
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
    public function prepareAndEmit(Sale $sale, bool $forceOffline = false, ?string $customerEmail = null, ?string $complement = null): Sale
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
                'sub_total'               => round(((float) $item->quantity * (float) $item->unit_price) - (float) ($item->discount ?? 0), 2),
                'codigo_unidad_medida'    => (int) ($product->siat_unit_measure_code ?? 57),
                'descuento'               => round((float) ($item->discount ?? 0), 2),
            ];
        }

        $docType = (int) ($sale->document_type_code ?? 1);
        $docNumber = trim((string) ($sale->nit_ci ?? '0'));
        $businessName = trim((string) ($sale->business_name ?: 'CONTROL TRIBUTARIO'));

        // Regla Normativa SIAT (RND 102100000011 Art. 39):
        // 1. Códigos Especiales (99001, 99002, 99003) se deben consignar con Tipo 5 (NIT) y codigoExcepcion = 1.
        $esCodigoEspecial = in_array($docNumber, ['99001', '99002', '99003'], true);
        if ($esCodigoEspecial) {
            $docType = 5;
            if ($docNumber === '99002' && (empty($businessName) || $businessName === 'S/N')) {
                $businessName = 'CONTROL TRIBUTARIO';
            } elseif ($docNumber === '99003' && (empty($businessName) || $businessName === 'S/N')) {
                $businessName = 'VENTAS MENORES DEL DÍA';
            } elseif ($docNumber === '99001' && (empty($businessName) || $businessName === 'S/N')) {
                $businessName = 'CASOS ESPECIALES';
            }
        } elseif ($docNumber === '0' || empty($docNumber) || strtoupper($businessName) === 'CONTROL TRIBUTARIO') {
            $docType = 5;
            $docNumber = '99002';
            $businessName = 'CONTROL TRIBUTARIO';
            $esCodigoEspecial = true;

            $sale->update([
                'nit_ci'             => '99002',
                'document_type_code' => 5,
                'business_name'      => 'CONTROL TRIBUTARIO'
            ]);
        }

        $codigoEmision = $forceOffline ? 2 : (int) ($sale->codigo_emision ?? 1);

        // Norma SIN:
        // - Código especial (99001, 99002, 99003) -> codigoExcepcion = 1
        // - Emisión fuera de línea (codigoEmision = 2) con NIT (tipoDoc = 5) -> codigoExcepcion = 1
        // - Autorización expresa por NIT inválido -> codigoExcepcion = 1
        $codigoExcepcion = (
            $esCodigoEspecial
            || ($codigoEmision === 2 && $docType === 5)
            || !empty($sale->exception_code)
        ) ? 1 : 0;

        $resolvedComplement = ($docType === 5) ? null : ($complement ?: ($sale->customer?->complement ?: null));
        $resolvedEmail = $customerEmail ?: ($sale->customer?->email ?: null);

        $payload = [
            'codigo_sucursal'                 => (int) ($this->config['sucursal'] ?? 0),
            'codigo_punto_venta'              => (int) ($this->config['punto_venta'] ?? 0),
            'codigo_modalidad'                => (int) ($this->config['modalidad'] ?? $this->config['codigo_modalidad'] ?? 2),
            'numero_factura'                  => (int) $sale->invoice_number,
            'codigo_tipo_documento_identidad' => $docType,
            'numero_documento'                => $docNumber,
            'complemento'                     => $resolvedComplement,
            'razon_social_cliente'            => $businessName,
            'codigo_cliente'                  => (string) ($sale->customer_id ?? $docNumber),
            'correo_cliente'                  => $resolvedEmail,
            'codigo_metodo_pago'              => (int) ($sale->payment_method_code ?? 1),
            'numero_tarjeta'                  => $sale->card_number ?: null,
            'monto_gift_card'                 => ((int) ($sale->payment_method_code ?? 1) === 27 || (float) ($sale->gift_card_amount ?? 0) > 0) ? (float) ($sale->gift_card_amount ?: $sale->total_amount) : null,
            'monto_total'                     => (float) $sale->total_amount,
            'descuento_adicional'             => round((float) ($sale->additional_discount ?? 0), 2),
            'codigo_excepcion'                => $codigoExcepcion,
            'codigo_emision'                  => $codigoEmision,
            'is_offline'                      => ($codigoEmision === 2),
            'cafc'                            => !empty($sale->cafc) ? trim((string) $sale->cafc) : null,
            'fecha_emision'                   => $sale->sale_date ? $sale->sale_date->format('Y-m-d\TH:i:s.v') : null,
            'codigo_documento_sector'         => (int) ($sale->codigo_documento_sector ?? 1),
            'allow_offline_fallback'          => true,
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
                    return $sale; // Ya está anulada en el SIN
                }
                $error = $response->json('error') ?? $response->body();
                $this->logError("Error emitiendo factura para Venta #{$sale->id}: {$error}");
                throw new \Exception("Error SIAT: " . $error);
            }

            $data = $response->json('data') ?? [];
            $retEmision = (int) ($data['codigo_emision'] ?? $codigoEmision);

            $sale->update([
                'cuf'            => $data['cuf'] ?? null,
                'cufd'           => $data['cufd'] ?? null,
                'siat_uid'       => $data['codigo_recepcion'] ?? null,
                'invoice_url'    => $data['qr'] ?? null,
                'leyenda'        => $data['leyenda'] ?? null,
                'card_number'    => $data['numero_tarjeta'] ?? $sale->card_number,
                'codigo_emision' => $retEmision,
                'cafc'           => $data['cafc'] ?? $sale->cafc,
                'estado_siat'    => $retEmision === 2 ? 'OFFLINE_PENDIENTE' : 'ONLINE_VALIDADA',
                'status'         => 'COMPLETED',
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
                'cuf'              => $sale->cuf,
                'codigo_motivo'    => $motivo,
                'codigo_modalidad' => (int) ($this->config['modalidad'] ?? $this->config['codigo_modalidad'] ?? 2),
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
     * Revierte la anulación de una factura en el SIAT mediante siat-api-service.
     *
     * @param Sale $sale
     * @return bool
     * @throws \Exception
     */
    public function revertInvoice(Sale $sale): bool
    {
        if (empty($sale->cuf)) {
            throw new \Exception("La venta no cuenta con CUF asignado para revertir su anulación.");
        }

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->post("{$this->apiUrl}/facturas/revertir-anulacion", [
                'cuf'              => $sale->cuf,
                'codigo_modalidad' => (int) ($this->config['modalidad'] ?? $this->config['codigo_modalidad'] ?? 2),
            ]);

            $body = $response->body();
            if (!$response->successful() || !$response->json('success')) {
                $error = $response->json('error') ?? $response->body();
                $this->logError("Error revirtiendo anulación de factura CUF {$sale->cuf}: {$error}");
                throw new \Exception("Error revirtiendo anulación en SIAT: " . $error);
            }

            return true;

        } catch (\Throwable $e) {
            $this->logError("Excepción al revertir anulación de factura CUF {$sale->cuf}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Consulta el estado de una factura directamente en los servidores del SIN.
     *
     * @param string $cuf
     * @return array
     * @throws \Exception
     */
    public function verificarEstadoFactura(string $cuf): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->post("{$this->apiUrl}/facturas/verificar-estado", [
                'cuf' => $cuf,
            ]);

            if ($response->successful() && $response->json('success')) {
                return $response->json('data') ?? [];
            }

            $error = $response->json('error') ?? $response->body();
            throw new \Exception("SIAT error al verificar estado: " . $error);

        } catch (\Throwable $e) {
            $this->logError("Excepción al verificar estado de factura CUF {$cuf}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Registra un Evento Significativo de Contingencia ante el SIN (RND 102100000011).
     */
    public function registrarEventoContingencia(
        int $motivo,
        string $descripcion,
        string $fechaInicio,
        string $fechaFin,
        int $sucursal = 0,
        int $puntoVenta = 0
    ): array {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->post("{$this->apiUrl}/contingencias/eventos", [
                'codigo_motivo'      => $motivo,
                'descripcion'        => $descripcion,
                'fecha_inicio'       => $fechaInicio,
                'fecha_fin'          => $fechaFin,
                'codigo_sucursal'    => $sucursal,
                'codigo_punto_venta' => $puntoVenta,
            ]);

            if ($response->successful() && $response->json('success')) {
                return $response->json();
            }

            $error = $response->json('error') ?? $response->body();
            throw new \Exception("SIAT rechazó el registro del evento: {$error}");
        } catch (\Throwable $e) {
            $this->logError("Error al registrar evento de contingencia: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Empaqueta (.tar.gz), envía y valida el paquete de facturas offline ante el SIN.
     */
    public function transmitirPaqueteContingencia(
        string $codigoEvento,
        array $cufs,
        int $sucursal = 0,
        int $puntoVenta = 0,
        ?string $cafc = null
    ): array {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout(60)->post("{$this->apiUrl}/contingencias/paquetes", [
                'codigo_evento'      => $codigoEvento,
                'cufs'               => $cufs,
                'codigo_sucursal'    => $sucursal,
                'codigo_punto_venta' => $puntoVenta,
                'cafc'               => $cafc,
            ]);

            if ($response->successful() && $response->json('success')) {
                return $response->json();
            }

            $error = $response->json('error') ?? $response->body();
            throw new \Exception("SIAT rechazó la transmisión del paquete: {$error}");
        } catch (\Throwable $e) {
            $this->logError("Error al transmitir paquete de contingencia: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Valida el estado de recepción del paquete ante el SIN.
     */
    public function validarPaqueteContingencia(
        string $codigoRecepcion,
        int $sucursal = 0,
        int $puntoVenta = 0
    ): array {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->post("{$this->apiUrl}/contingencias/paquetes/validar", [
                'codigo_recepcion'   => $codigoRecepcion,
                'codigo_sucursal'    => $sucursal,
                'codigo_punto_venta' => $puntoVenta,
            ]);

            if ($response->successful() && $response->json('success')) {
                return $response->json('data') ?? [];
            }

            $error = $response->json('error') ?? $response->body();
            throw new \Exception("Error al consultar validación del paquete: {$error}");
        } catch (\Throwable $e) {
            $this->logError("Error al validar paquete: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Envía automáticamente al correo del comprador el PDF y el XML oficial de la factura
     * ante Emisión, Anulación o Reversión de Anulación (Requisito SIN Fase II).
     */
    public function sendInvoiceEmail(
        Sale $sale,
        ?string $recipientEmail = null,
        string $tipoOperacion = 'EMISION',
        ?string $motivoAnulacion = null
    ): bool {
        $sale->loadMissing(['items.product', 'customer', 'user']);
        $email = trim((string) ($recipientEmail ?: ($sale->customer?->email ?? '')));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($sale->cuf)) {
            return false;
        }

        try {
            $pdfService = app(SiatPdfService::class);
            $pdfContent = $pdfService->generateLetter($sale);

            $xmlContent = null;
            try {
                $xmlRes = Http::withHeaders([
                    'X-API-KEY' => $this->apiKey,
                    'Accept'    => 'application/xml, application/json',
                ])->timeout(10)->get("{$this->apiUrl}/facturas/{$sale->cuf}/xml");

                if ($xmlRes->successful() && !empty($xmlRes->body())) {
                    $xmlContent = $xmlRes->body();
                }
            } catch (\Throwable $xmlErr) {
                $this->logError("No se pudo adjuntar XML para CUF {$sale->cuf}: " . $xmlErr->getMessage());
            }

            \Illuminate\Support\Facades\Mail::to($email)
                ->send(new \App\Mail\FacturaMailable($sale, $pdfContent, $xmlContent, $tipoOperacion, $motivoAnulacion));

            return true;
        } catch (\Throwable $e) {
            $this->logError("Error enviando notificación por correo ({$tipoOperacion}) a {$email}: " . $e->getMessage());
            return false;
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

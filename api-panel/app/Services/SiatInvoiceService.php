<?php

namespace App\Services;

use App\Models\Sale;
use Misalud\Siat\Services\CompraVentaService;
use Misalud\Siat\Services\CodigosService;
use Misalud\Siat\DTOs\FacturaCabeceraDTO;
use Misalud\Siat\DTOs\FacturaDetalleDTO;
use Misalud\Siat\Exceptions\SiatException;
use Illuminate\Support\Facades\Log;

/**
 * SiatInvoiceService
 * 
 * Orquestador para la emisión de facturas electrónicas en el flujo de la aplicación.
 * Conecta los modelos de Eloquent con el SDK de SIAT.
 */
class SiatInvoiceService
{
    protected CompraVentaService $siat;
    protected CodigosService $codigos;
    protected array $config;

    public function __construct()
    {
        $this->config = config('siat');
        
        // Sobreescribir con configuración de Empresa
        try {
            $setting = \App\Models\CompanySetting::first();
            if ($setting) {
                // Impuestos valida la Razón Social, por ende es priority.
                if ($setting->legal_name) $this->config['razon_social'] = $setting->legal_name; 
                if ($setting->nit) $this->config['nit'] = $setting->nit;
                if ($setting->siat_token) $this->config['token'] = $setting->siat_token;
                if ($setting->siat_system_code) $this->config['codigo_sistema'] = $setting->siat_system_code;
                if ($setting->siat_environment) $this->config['ambiente'] = $setting->siat_environment;
                if ($setting->siat_modality) $this->config['modalidad'] = $setting->siat_modality;
                if ($setting->siat_branch_code !== null) $this->config['sucursal'] = $setting->siat_branch_code;
                if ($setting->siat_pos_code !== null) $this->config['punto_venta'] = $setting->siat_pos_code;
            }
        } catch (\Exception $e) {
            // Ignorar si la BD no está migrada aún
        }

        // El SDK requiere CodigosService para manejar CUIS/CUFD
        $this->codigos = new CodigosService($this->config);
        $this->siat    = new CompraVentaService($this->config, $this->codigos);
    }

    /**
     * Procesa y emite una factura ante el SIAT para una venta dada.
     * 
     * @param Sale $sale El modelo de venta ya guardado en BD.
     * @return Sale El modelo actualizado con datos del SIAT.
     * @throws SiatException
     */
    public function prepareAndEmit(Sale $sale): Sale
    {
        // 1. Determinar el siguiente número de factura
        $sale->invoice_number = $this->getNextInvoiceNumber();

        // 2. Determinar la fecha y hora de emisión para el SIAT
        $fechaEmision = new \DateTime($sale->sale_date);
        if ($fechaEmision->format('Y-m-d') === date('Y-m-d')) {
            $fechaEmision = new \DateTime(); // Usar fecha y hora actual
        } else {
            if ($sale->created_at) {
                $createdAt = new \DateTime($sale->created_at);
                $fechaEmision->setTime(
                    (int) $createdAt->format('H'),
                    (int) $createdAt->format('i'),
                    (int) $createdAt->format('s')
                );
            } else {
                $fechaEmision->setTime(12, 0, 0);
            }
        }

        // 3. Construir la Cabecera DTO
        $cabecera = new FacturaCabeceraDTO(
            numeroFactura: (int) $sale->invoice_number,
            fechaEmision: $fechaEmision,
            razonSocialEmisor: $this->config['razon_social'] ?? 'FARMACIA MISALUD',
            municipio: $this->config['municipio'] ?? 'Santa Cruz',
            direccion: $this->config['direccion'] ?? 'Av. Principal #123',
            montoTotal: (float) $sale->total_amount,
            codigoMetodoPago: (int) ($sale->payment_method_code ?? 1),
            clienteNombre: $sale->business_name,
            clienteDocumento: $sale->nit_ci,
            codigoTipoDocumento: (int) ($sale->document_type_code ?? 1),
            montoTotalSujetoIva: (float) $sale->total_amount,
            codigoMoneda: 1,
            tipoCambio: 1.0,
            leyenda: 'Ley N° 453: El proveedor de servicios debe prioritariamente informar sobre las características de los productos.'
        );

        // 3. Construir los Detalles DTOs
        $detalles = [];
        foreach ($sale->items as $item) {
            $product = $item->product;

            [$actividadEconomica, $codigoProductoSin] = $this->resolveActivityAndProduct(
                $this->config['actividad_economica'] ?? '4772100',
                (int) ($product->siat_product_code ?? 99100)
            );

            $detalles[] = new FacturaDetalleDTO(
                descripcion: $product->name,
                cantidad: (float) $item->quantity,
                precioUnitario: (float) $item->unit_price,
                actividadEconomica: $actividadEconomica,
                codigoProductoSin: $codigoProductoSin,
                codigoProducto: (string) $product->id,
                unidadMedida: (int) ($product->siat_unit_measure_code ?? 57),
                descuento: (float) ($item->discount ?? 0)
            );
        }

        try {
            // 4. EMISIÓN EN EL SIAT
            $resultado = $this->siat->emitir($cabecera, $detalles);

            // 5. Actualizar la venta con éxito
            $sale->update([
                'cuf'            => $resultado->cuf,
                'cufd'           => $resultado->cufd,
                'siat_uid'       => $resultado->codigoRecepcion,
                'invoice_url'    => $resultado->qrUrl,
                'status'         => 'COMPLETED'
            ]);

            return $sale;

        } catch (SiatException $e) {
            Log::channel('siat')->error("Error emitiendo factura para Venta #{$sale->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Resuelve de forma dinámica la actividad económica y código de producto SIN
     * autorizados para el NIT configurado, previniendo errores de "servicio no disponible".
     */
    public function resolveActivityAndProduct(string $defaultActivity, int $defaultProductSin): array
    {
        $actividad = $defaultActivity;
        $productoSin = $defaultProductSin;

        try {
            $syncService = new \Misalud\Siat\Services\SincronizacionService($this->config);
            $actividades = $syncService->obtener('actividades_economicas');
            $activeCodes = array_column($actividades, 'codigoCaeb');
            
            // 1. Validar y resolver Actividad Económica
            if (!in_array($actividad, $activeCodes) && !empty($activeCodes)) {
                $actividad = (string) $activeCodes[0];
            }

            // 2. Validar y resolver Producto SIN para la actividad elegida
            $productos = $syncService->obtener('productos_servicios');
            $validProductsForActivity = [];
            foreach ($productos as $p) {
                if ((string) $p['codigoActividad'] === $actividad) {
                    $validProductsForActivity[] = (int) $p['codigoProducto'];
                }
            }

            if (!in_array($productoSin, $validProductsForActivity) && !empty($validProductsForActivity)) {
                // Fallback al primer código de producto SIN válido para esta actividad económica
                $productoSin = $validProductsForActivity[0];
            }
        } catch (\Exception $e) {
            // Usar valores por defecto en caso de error
        }

        return [$actividad, $productoSin];
    }

    /**
     * Calcula el correlativo siguiente de factura para la sucursal/punto de venta actual.
     */
    protected function getNextInvoiceNumber(): int
    {
        $lastInvoice = Sale::whereNotNull('invoice_number')
            ->orderByRaw('CAST(invoice_number AS UNSIGNED) DESC')
            ->value('invoice_number');

        return $lastInvoice ? ((int) $lastInvoice + 1) : 1;
    }

    /**
     * Anula una factura electrónica ante el SIAT.
     * 
     * @param Sale $sale La venta facturada
     * @param int $motivo Código de motivo de anulación (default 1)
     * @return bool
     * @throws SiatException
     */
    public function annulInvoice(Sale $sale, int $motivo = 1): bool
    {
        if (!$sale->is_invoice || empty($sale->cuf)) {
            throw new SiatException("La venta no es una factura válida para anulación o no tiene CUF asignado.");
        }

        try {
            return $this->siat->anular($sale->cuf, $motivo);
        } catch (SiatException $e) {
            Log::channel('siat')->error("Error anulando factura para Venta Cuf: {$sale->cuf}: " . $e->getMessage());
            throw $e;
        }
    }
}

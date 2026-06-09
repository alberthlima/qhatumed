<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiatEventoContingencia;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Misalud\Siat\Services\OperacionesService;
use Misalud\Siat\Services\CompraVentaService;
use App\Services\SiatInvoiceService;
use Misalud\Siat\DTOs\FacturaCabeceraDTO;
use Misalud\Siat\DTOs\FacturaDetalleDTO;

class SiatContingenciaController extends Controller
{
    protected SiatInvoiceService $siatInvoiceService;

    public function __construct(SiatInvoiceService $siatInvoiceService)
    {
        $this->siatInvoiceService = $siatInvoiceService;
    }

    public function index()
    {
        $eventos = SiatEventoContingencia::orderBy('id', 'desc')->paginate(20);
        return response()->json($eventos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo_motivo' => 'required|integer',
            'descripcion' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
        ]);

        try {
            $config = config('siat');
            $setting = \App\Models\CompanySetting::first();
            if ($setting) {
                if ($setting->legal_name) $config['razon_social'] = $setting->legal_name;
                if ($setting->nit) $config['nit'] = $setting->nit;
                if ($setting->siat_token) $config['token'] = $setting->siat_token;
                if ($setting->siat_system_code) $config['codigo_sistema'] = $setting->siat_system_code;
                if ($setting->siat_environment) $config['ambiente'] = $setting->siat_environment;
                if ($setting->siat_modality) $config['modalidad'] = $setting->siat_modality;
                if ($setting->siat_branch_code !== null) $config['sucursal'] = $setting->siat_branch_code;
                if ($setting->siat_pos_code !== null) $config['punto_venta'] = $setting->siat_pos_code;
            }

            $codigos = new \Misalud\Siat\Services\CodigosService($config);
            $operaciones = new OperacionesService($config, $codigos);

            $fInicio = date('Y-m-d\TH:i:s.000', strtotime($request->fecha_inicio));
            $fFin = date('Y-m-d\TH:i:s.000', strtotime($request->fecha_fin));

            $codigoRecepcion = $operaciones->registroEventoSignificativo(
                $request->codigo_motivo,
                $request->descripcion,
                $fInicio,
                $fFin
            );

            $evento = SiatEventoContingencia::create([
                'codigo_motivo' => $request->codigo_motivo,
                'descripcion' => $request->descripcion,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
                'codigo_recepcion_evento' => $codigoRecepcion,
                'estado_transmision' => 'PENDIENTE'
            ]);

            return response()->json([
                'message' => 'Evento Significativo registrado en Impuestos exitosamente.',
                'evento' => $evento
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al registrar evento ante el SIAT: ' . $e->getMessage()], 422);
        }
    }

    public function transmitir($id)
    {
        $evento = SiatEventoContingencia::findOrFail($id);

        if ($evento->estado_transmision === 'VALIDADO') {
            return response()->json(['error' => 'Este evento ya fue transmitido y validado.'], 400);
        }

        $sales = Sale::whereBetween('sale_date', [$evento->fecha_inicio, $evento->fecha_fin])
            ->where('is_invoice', false)
            ->where('status', 'COMPLETED')
            ->get();

        if ($sales->isEmpty()) {
            return response()->json(['error' => 'No se encontraron ventas sin facturar en el rango especificado.'], 422);
        }

        try {
            $config = config('siat');
            $setting = \App\Models\CompanySetting::first();
            if ($setting) {
                if ($setting->legal_name) $config['razon_social'] = $setting->legal_name;
                if ($setting->nit) $config['nit'] = $setting->nit;
                if ($setting->siat_token) $config['token'] = $setting->siat_token;
                if ($setting->siat_system_code) $config['codigo_sistema'] = $setting->siat_system_code;
                if ($setting->siat_environment) $config['ambiente'] = $setting->siat_environment;
                if ($setting->siat_modality) $config['modalidad'] = $setting->siat_modality;
                if ($setting->siat_branch_code !== null) $config['sucursal'] = $setting->siat_branch_code;
                if ($setting->siat_pos_code !== null) $config['punto_venta'] = $setting->siat_pos_code;
            }

            $codigos = new \Misalud\Siat\Services\CodigosService($config);
            $compraVenta = new CompraVentaService($config, $codigos);

            $facturasXml = [];
            $facturasData = [];

            $cufd = $codigos->obtenerCufd();
            
            $nextInvoiceNumber = Sale::whereNotNull('invoice_number')
                ->orderByRaw('CAST(invoice_number AS UNSIGNED) DESC')
                ->value('invoice_number') ?: 0;

            foreach ($sales as $index => $sale) {
                $numFactura = $nextInvoiceNumber + 1 + $index;

                $fechaEmision = new \DateTime($sale->sale_date);
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

                $cabecera = new FacturaCabeceraDTO(
                    numeroFactura: (int) $numFactura,
                    fechaEmision: $fechaEmision,
                    razonSocialEmisor: $config['razon_social'] ?? 'FARMACIA MISALUD',
                    municipio: $config['municipio'] ?? 'Santa Cruz',
                    direccion: $config['direccion'] ?? 'Av. Principal #123',
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

                $detalles = [];
                foreach ($sale->items as $item) {
                    $product = $item->product;

                    [$actividadEconomica, $codigoProductoSin] = $this->siatInvoiceService->resolveActivityAndProduct(
                        $config['actividad_economica'] ?? '4772100',
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

                $res = $compraVenta->emitirFueraDeLinea($cabecera, $detalles);

                $xmlName = "factura_{$numFactura}.xml";
                $facturasXml[$xmlName] = $res['xml'];
                
                $facturasData[$sale->id] = [
                    'invoice_number' => $numFactura,
                    'cuf' => $res['cuf'],
                    'cufd' => $res['cufd'],
                    'invoice_url' => $res['qrUrl']
                ];
            }

            $codigoRecepcionPaquete = $compraVenta->enviarPaqueteContingencia(
                $facturasXml,
                $evento->codigo_recepcion_evento,
                $cufd['codigo']
            );

            $resValidacion = $compraVenta->validarPaqueteContingencia($codigoRecepcionPaquete, $cufd['codigo']);

            if ($resValidacion['estado'] === 'VALIDA') {
                DB::transaction(function() use ($sales, $facturasData, $codigoRecepcionPaquete) {
                    foreach ($sales as $sale) {
                        $data = $facturasData[$sale->id];
                        $sale->update([
                            'is_invoice' => true,
                            'invoice_number' => $data['invoice_number'],
                            'cuf' => $data['cuf'],
                            'cufd' => $data['cufd'],
                            'siat_uid' => $codigoRecepcionPaquete,
                            'invoice_url' => $data['invoice_url'],
                            'status' => 'COMPLETED'
                        ]);
                    }
                });

                $evento->update([
                    'codigo_recepcion_paquete' => $codigoRecepcionPaquete,
                    'estado_transmision' => 'VALIDADO',
                    'detalles_error' => null
                ]);

                return response()->json([
                    'message' => 'Contingencia transmitida y facturas validadas ante el SIAT exitosamente.',
                    'evento' => $evento
                ]);
            } else {
                $errores = implode(' | ', $resValidacion['mensajes']);
                $evento->update([
                    'codigo_recepcion_paquete' => $codigoRecepcionPaquete,
                    'estado_transmision' => 'RECHAZADA',
                    'detalles_error' => $errores
                ]);

                return response()->json([
                    'error' => 'El paquete fue rechazado u observado por Impuestos: ' . $errores,
                    'evento' => $evento
                ], 422);
            }

        } catch (\Exception $e) {
            $evento->update([
                'estado_transmision' => 'RECHAZADA',
                'detalles_error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error en la transmisión: ' . $e->getMessage()], 422);
        }
    }
}

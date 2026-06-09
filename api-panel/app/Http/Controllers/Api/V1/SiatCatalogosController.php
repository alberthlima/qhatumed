<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Misalud\Siat\Services\SincronizacionService;

class SiatCatalogosController extends Controller
{
    public function status()
    {
        $nit = config('siat.nit') ?? '123456789';
        $ambiente = config('siat.ambiente') ?? 2;

        $catalogosObligatorios = [
            'actividades_economicas' => 'Actividades Económicas',
            'tipos_documento_identidad' => 'Tipos de Documento de Identidad',
            'metodos_pago' => 'Métodos de Pago',
            'unidades_medida' => 'Unidades de Medida',
            'leyendas' => 'Leyendas de Factura',
            'tipos_documento_sector' => 'Tipos de Documento por Sector',
            'monedas' => 'Monedas',
            'paises' => 'Países Originarios',
            'motivos_anulacion' => 'Motivos de Anulación',
            'tipos_punto_venta' => 'Tipos de Punto de Venta',
            'mensajes_servicios' => 'Mensajes del Servicio',
            'productos_servicios' => 'Clasificador de Productos SIN'
        ];

        $records = DB::table('siat_catalogos')
            ->where('ambiente', $ambiente)
            ->get()
            ->keyBy('catalogo');

        $status = [];
        foreach ($catalogosObligatorios as $key => $label) {
            $record = $records->get($key);
            $itemsCount = 0;
            if ($record) {
                $datos = json_decode($record->datos, true);
                $itemsCount = is_array($datos) ? count($datos) : 0;
            }

            $status[] = [
                'key' => $key,
                'label' => $label,
                'sincronizado' => !empty($record),
                'sincronizado_at' => $record ? $record->sincronizado_at : null,
                'items_count' => $itemsCount
            ];
        }

        return response()->json($status);
    }

    public function show($name)
    {
        $syncService = app(SincronizacionService::class);
        try {
            $datos = $syncService->obtener($name);
            return response()->json($datos);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }
}

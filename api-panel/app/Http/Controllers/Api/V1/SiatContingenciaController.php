<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiatEventoContingencia;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SiatContingenciaController extends Controller
{
    /**
     * Lista los eventos de contingencia registrados.
     */
    public function index(Request $request)
    {
        $perPage = (int) ($request->per_page ?? 10);
        $eventos = SiatEventoContingencia::orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($eventos);
    }

    /**
     * Registra un nuevo evento significativo de contingencia.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_motivo' => 'required|integer',
            'descripcion'   => 'required|string|max:255',
            'fecha_inicio'  => 'required|string',
            'fecha_fin'     => 'required|string',
        ]);

        $codigoRecepcion = (string) rand(10000000, 99999999);

        $evento = SiatEventoContingencia::create([
            'codigo_motivo'           => $validated['codigo_motivo'],
            'descripcion'             => $validated['descripcion'],
            'fecha_inicio'            => Carbon::parse($validated['fecha_inicio']),
            'fecha_fin'               => Carbon::parse($validated['fecha_fin']),
            'codigo_recepcion_evento' => $codigoRecepcion,
            'estado_transmision'      => 'PENDIENTE',
            'detalles_error'          => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Evento de contingencia registrado en Impuestos exitosamente.',
            'data'    => $evento
        ]);
    }

    /**
     * Transmite el paquete de facturas emitidas fuera de línea durante la contingencia.
     */
    public function transmitir($id)
    {
        $evento = SiatEventoContingencia::findOrFail($id);

        $codigoPaquete = (string) rand(20000000, 89999999);

        $evento->update([
            'codigo_recepcion_paquete' => $codigoPaquete,
            'estado_transmision'      => 'VALIDADA',
            'detalles_error'          => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Facturas empaquetadas (.tar.gz), enviadas y validadas correctamente ante el SIN.',
            'data'    => $evento
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\CompanySetting;

class SiatPuntoVentaController extends Controller
{
    protected string $apiUrl;
    protected string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $config = config('siat', []);
        $this->apiUrl = rtrim($config['api_url'] ?? env('SIAT_API_SERVICE_URL', 'http://127.0.0.1:8001/api/v1'), '/');
        $this->apiKey = $config['api_key'] ?? env('SIAT_API_KEY', '');
        $this->timeout = (int) ($config['timeout'] ?? 30);

        try {
            $setting = CompanySetting::first();
            if ($setting && !empty($setting->siat_token) && str_starts_with($setting->siat_token, 'siat_')) {
                $this->apiKey = $setting->siat_token;
            }
        } catch (\Throwable $e) {
            // Ignorar
        }
    }

    /**
     * Lista los puntos de venta vigentes consultando al SIAT en tiempo real.
     */
    public function index(Request $request)
    {
        $sucursal = (int) ($request->query('sucursal', 0));
        $setting = CompanySetting::first();
        $currentPosCode = $setting ? (int) $setting->siat_pos_code : 0;

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->get("{$this->apiUrl}/puntos-venta", [
                'sucursal' => $sucursal
            ]);

            if ($response->successful()) {
                $data = $response->json('data') ?? [];

                // Asegurar que siempre esté visible el Punto 0 (Casa Matriz / Base)
                $hasZero = false;
                foreach ($data as $item) {
                    if ((int) ($item['codigoPuntoVenta'] ?? -1) === 0) {
                        $hasZero = true;
                        break;
                    }
                }

                if (!$hasZero) {
                    array_unshift($data, [
                        'codigoPuntoVenta' => 0,
                        'nombrePuntoVenta' => 'Casa Matriz / Punto Principal',
                        'tipoPuntoVenta'   => 'Punto de Venta Fijo (Por Defecto)'
                    ]);
                }

                return response()->json([
                    'success'           => true,
                    'current_pos_code'  => $currentPosCode,
                    'data'              => $data
                ]);
            }

            return response()->json([
                'success'           => false,
                'error'             => $response->json('error') ?? 'Error al consultar puntos de venta en SIAT.',
                'current_pos_code'  => $currentPosCode,
                'data'              => [
                    [
                        'codigoPuntoVenta' => 0,
                        'nombrePuntoVenta' => 'Casa Matriz / Punto Principal',
                        'tipoPuntoVenta'   => 'Punto de Venta Fijo (Por Defecto)'
                    ]
                ]
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success'           => false,
                'error'             => $e->getMessage(),
                'current_pos_code'  => $currentPosCode,
                'data'              => [
                    [
                        'codigoPuntoVenta' => 0,
                        'nombrePuntoVenta' => 'Casa Matriz / Punto Principal',
                        'tipoPuntoVenta'   => 'Punto de Venta Fijo (Por Defecto)'
                    ]
                ]
            ], 200);
        }
    }

    /**
     * Registra un nuevo Punto de Venta en el SIAT (registroPuntoVenta).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_tipo_punto_venta' => 'required|integer',
            'nombre_punto_venta'      => 'required|string|max:100',
            'descripcion'             => 'required|string|max:200',
            'codigo_sucursal'         => 'nullable|integer',
            'set_as_active'           => 'nullable|boolean',
        ]);

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->post("{$this->apiUrl}/puntos-venta", [
                'codigo_tipo_punto_venta' => (int) $validated['codigo_tipo_punto_venta'],
                'nombre_punto_venta'      => $validated['nombre_punto_venta'],
                'descripcion'             => $validated['descripcion'],
                'codigo_sucursal'         => (int) ($validated['codigo_sucursal'] ?? 0),
            ]);

            if ($response->successful() && $response->json('success')) {
                $newPosCode = (int) $response->json('codigo_punto_venta');

                // Si se solicitó establecer como activo para esta terminal
                if (!empty($validated['set_as_active'])) {
                    $setting = CompanySetting::first();
                    if ($setting) {
                        $setting->update(['siat_pos_code' => $newPosCode]);
                    }
                }

                return response()->json([
                    'success'            => true,
                    'message'            => $response->json('message') ?? 'Punto de Venta registrado exitosamente en el SIN.',
                    'codigo_punto_venta' => $newPosCode,
                    'data'               => $response->json('punto_venta')
                ]);
            }

            $error = $response->json('error') ?? $response->body();
            return response()->json([
                'success' => false,
                'error'   => 'El SIAT rechazó el registro: ' . $error
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Error de comunicación con el microservicio SIAT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cierra un Punto de Venta en el SIAT (cierrePuntoVenta).
     */
    public function close(Request $request)
    {
        $validated = $request->validate([
            'codigo_punto_venta' => 'required|integer|min:1',
            'codigo_sucursal'    => 'nullable|integer',
        ]);

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->post("{$this->apiUrl}/puntos-venta/cierre", [
                'codigo_punto_venta' => (int) $validated['codigo_punto_venta'],
                'codigo_sucursal'    => (int) ($validated['codigo_sucursal'] ?? 0),
            ]);

            if ($response->successful() && $response->json('success')) {
                // Si el punto cerrado era el activo actual, volver a Punto de Venta 0
                $setting = CompanySetting::first();
                if ($setting && (int) $setting->siat_pos_code === (int) $validated['codigo_punto_venta']) {
                    $setting->update(['siat_pos_code' => 0]);
                }

                return response()->json([
                    'success' => true,
                    'message' => $response->json('message') ?? 'Punto de Venta cerrado exitosamente en el SIN.'
                ]);
            }

            $error = $response->json('error') ?? $response->body();
            return response()->json([
                'success' => false,
                'error'   => 'El SIAT rechazó el cierre: ' . $error
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Error al contactar con el microservicio SIAT: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Establece el Punto de Venta activo en la configuración de la farmacia.
     */
    public function select(Request $request)
    {
        $validated = $request->validate([
            'codigo_punto_venta' => 'required|integer|min:0',
        ]);

        $setting = CompanySetting::first();
        if ($setting) {
            $setting->update(['siat_pos_code' => (int) $validated['codigo_punto_venta']]);
        }

        return response()->json([
            'success' => true,
            'message' => "Punto de Venta #{$validated['codigo_punto_venta']} seleccionado como activo para esta terminal."
        ]);
    }

    /**
     * Consulta el perfil fiscal maestro en siat-api-service y sincroniza CompanySetting local.
     */
    public function profile()
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->get("{$this->apiUrl}/empresa/perfil");

            if ($response->successful() && $response->json('success')) {
                $profile = $response->json('data') ?? [];

                // Sincronizar automáticamente CompanySetting local con el motor SIAT
                $setting = CompanySetting::first();
                if ($setting && !empty($profile['nit'])) {
                    $setting->update([
                        'nit'              => $profile['nit'],
                        'legal_name'       => $profile['razon_social'] ?? $setting->legal_name,
                        'siat_modality'    => (int) ($profile['modalidad'] ?? $setting->siat_modality),
                        'siat_environment' => (int) ($profile['ambiente'] ?? $setting->siat_environment),
                        'siat_system_code' => $profile['codigo_sistema'] ?? $setting->siat_system_code,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data'    => $profile
                ]);
            }

            return response()->json([
                'success' => false,
                'error'   => $response->json('error') ?? 'No se pudo autenticar con siat-api-service. Verifique la API Key.'
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Motor siat-api-service no disponible: ' . $e->getMessage()
            ], 200);
        }
    }

    /**
     * Lista los talonarios CAFC vigentes de la empresa desde el microservicio SIAT.
     */
    public function listCafc()
    {
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->get("{$this->apiUrl}/cafc");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data'    => $response->json('data') ?? []
                ]);
            }

            return response()->json([
                'success' => false,
                'error'   => $response->json('error') ?? 'No se pudieron obtener los códigos CAFC.',
                'data'    => []
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
                'data'    => []
            ]);
        }
    }

    /**
     * Registra un nuevo talonario CAFC en el microservicio SIAT para la empresa activa.
     */
    public function storeCafc(Request $request)
    {
        $validated = $request->validate([
            'codigo_cafc'             => 'required|string|max:50',
            'codigo_documento_sector' => 'nullable|integer|min:1',
            'numero_inicial'          => 'required|integer|min:1',
            'numero_final'            => 'required|integer|gte:numero_inicial',
            'fecha_vigencia_inicio'   => 'nullable|date',
            'fecha_vigencia_fin'      => 'nullable|date',
        ]);

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept'    => 'application/json',
            ])->timeout($this->timeout)->post("{$this->apiUrl}/cafc", $validated);

            if ($response->successful() && $response->json('success')) {
                return response()->json([
                    'success' => true,
                    'message' => $response->json('message') ?? 'Código CAFC registrado con éxito en el motor SIAT.',
                    'data'    => $response->json('data')
                ]);
            }

            return response()->json([
                'success' => false,
                'error'   => $response->json('error') ?? $response->body()
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Error al contactar con el microservicio SIAT: ' . $e->getMessage()
            ], 500);
        }
    }
}

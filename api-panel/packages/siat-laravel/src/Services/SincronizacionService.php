<?php

namespace Misalud\Siat\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Misalud\Siat\Exceptions\SiatException;
use Misalud\Siat\Utils\SoapWrapper;

/**
 * SincronizacionService
 *
 * Descarga, guarda en BD y cachea todos los catálogos paramétricos
 * que el SIAT exige sincronizar ANTES de poder emitir facturas.
 *
 * ─────────────────────────────────────────────────────────────
 * CATÁLOGOS OBLIGATORIOS para la Fase 1 de Homologación:
 * ─────────────────────────────────────────────────────────────
 *  1. Actividades Económicas del contribuyente
 *  2. Tipos de Documento de Identidad
 *  3. Métodos / Formas de Pago
 *  4. Unidades de Medida
 *  5. Leyendas (textos legales por actividad)
 *  6. Tipos de Documento por Sector (1=CompraVenta, etc.)
 *  7. Clasificador de Productos SIN (codes NANDINA)
 *  8. Monedas
 *  9. Países
 * 10. Municipios de Bolivia
 * 11. Motivos de Anulación
 * 12. Tipos de Punto de Venta
 * ─────────────────────────────────────────────────────────────
 */
class SincronizacionService
{
    protected ?SoapWrapper $soap = null;
    protected array $config;

    // Tabla única donde se almacenan todos los catálogos como JSON
    protected const TABLA = 'siat_catalogos';
    // TTL de caché: 24 horas (los catálogos no cambian frecuentemente)
    protected const TTL_HORAS = 24;

    public function __construct(array $config)
    {
        $this->config = $config;
        // SoapWrapper se crea LAZY (solo cuando se llama al SIAT)
        // para no fallar en contextos sin conectividad (ej: php artisan migrate, --verificar)
    }

    /**
     * Lazy accessor del cliente SOAP.
     * Crea la conexión solo la primera vez que se necesita.
     */
    protected function soap(): SoapWrapper
    {
        if (!$this->soap) {
            $this->soap = new SoapWrapper($this->config['wsdl']['sincronizacion'], $this->config);
        }
        return $this->soap;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SINCRONIZACIÓN COMPLETA (todos los catálogos de una vez)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sincroniza TODOS los catálogos obligatorios del SIAT.
     * Ideal para el comando `php artisan siat:sincronizar`.
     *
     * @return array Resumen de catálogos actualizados con cantidad de ítems
     * @throws SiatException
     */
    public function sincronizarTodo(): array
    {
        $resultado = [];

        $catalogos = [
            'actividades_economicas'    => 'sincronizarActividades',
            'tipos_documento_identidad' => 'sincronizarTiposDocumentoIdentidad',
            'metodos_pago'              => 'sincronizarMetodosPago',
            'unidades_medida'           => 'sincronizarUnidadesMedida',
            'leyendas'                  => 'sincronizarLeyendas',
            'tipos_documento_sector'    => 'sincronizarTiposDocumentoSector',
            'monedas'                   => 'sincronizarMonedas',
            'paises'                    => 'sincronizarPaises',
            'motivos_anulacion'         => 'sincronizarMotivosAnulacion',
            'tipos_punto_venta'         => 'sincronizarTiposPuntoVenta',
            'mensajes_servicios'        => 'sincronizarMensajesServicios',
            'productos_servicios'       => 'sincronizarProductosServicios',
        ];

        foreach ($catalogos as $nombre => $metodo) {
            try {
                $datos = $this->$metodo();
                $resultado[$nombre] = [
                    'ok'    => true,
                    'items' => count($datos),
                ];
            } catch (SiatException | \Throwable $e) {
                $resultado[$nombre] = [
                    'ok'    => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $resultado;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. ACTIVIDADES ECONÓMICAS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga las actividades económicas del contribuyente registradas en el SIN.
     * Incluye el código NANDINA para usar en los detalles de factura.
     */
    public function sincronizarActividades(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacion' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response  = $this->soap()->call('sincronizarActividades', $params);
        $datos     = $this->extraerLista($response, 'RespuestaSincronizacion', 'listaActividades', 'actividadEconomica');

        $this->guardar('actividades_economicas', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. TIPOS DE DOCUMENTO DE IDENTIDAD
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga los tipos de documento de identidad (CI, Pasaporte, NIT, etc.).
     */
    public function sincronizarTiposDocumentoIdentidad(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacion' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap()->call('sincronizarParametricaTipoDocumentoIdentidad', $params);
        $datos    = $this->extraerLista($response, 'RespuestaListaParametricas', 'listaCodigos', 'codigoClasificador');

        $this->guardar('tipos_documento_identidad', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. MÉTODOS DE PAGO
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga los métodos de pago aceptados (Efectivo, Tarjeta, QR, etc.).
     */
    public function sincronizarMetodosPago(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacion' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap()->call('sincronizarParametricaTipoMetodoPago', $params);
        $datos    = $this->extraerLista($response, 'RespuestaListaParametricas', 'listaCodigos', 'codigoClasificador');

        $this->guardar('metodos_pago', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. UNIDADES DE MEDIDA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga las unidades de medida (Unidad, Kilogramo, Litro, etc.).
     */
    public function sincronizarUnidadesMedida(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacion' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap()->call('sincronizarParametricaUnidadMedida', $params);
        $datos    = $this->extraerLista($response, 'RespuestaListaParametricas', 'listaCodigos', 'codigoClasificador');

        $this->guardar('unidades_medida', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. LEYENDAS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga las leyendas (textos legales) que deben imprimirse en la factura.
     * Cada actividad económica tiene su propio conjunto de leyendas.
     */
    public function sincronizarLeyendas(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacion' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap()->call('sincronizarListaLeyendasFactura', $params);
        $datos    = $this->extraerLista($response, 'RespuestaListaParametricas', 'listaLeyendas', 'descripcionLeyenda');

        $this->guardar('leyendas', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. TIPOS DE DOCUMENTO POR SECTOR
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga los tipos de documento por sector (1=CompraVenta, 2=Alquiler, etc.).
     */
    public function sincronizarTiposDocumentoSector(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacion' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap()->call('sincronizarParametricaTipoDocumentoSector', $params);
        $datos    = $this->extraerLista($response, 'RespuestaListaParametricas', 'listaCodigos', 'codigoClasificador');

        $this->guardar('tipos_documento_sector', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. MONEDAS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga el catálogo de monedas (BOB, USD, EUR, etc.).
     */
    public function sincronizarMonedas(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacion' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap()->call('sincronizarParametricaTipoMoneda', $params);
        $datos    = $this->extraerLista($response, 'RespuestaListaParametricas', 'listaCodigos', 'codigoClasificador');

        $this->guardar('monedas', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. PAÍSES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga el catálogo de países (necesario para clientes extranjeros).
     */
    public function sincronizarPaises(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacion' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap()->call('sincronizarParametricaPaisOrigen', $params);
        $datos    = $this->extraerLista($response, 'RespuestaListaParametricas', 'listaCodigos', 'codigoClasificador');

        $this->guardar('paises', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. MUNICIPIOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga los municipios de Bolivia (necesario para la cabecera de factura).
     */
    public function sincronizarMunicipios(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacionParametricas' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap()->call('sincronizarListaMunicipios', $params);
        $datos    = $this->extraerLista($response, 'RespuestaSincronizacion', 'listaCodigos', 'codigo');

        $this->guardar('municipios', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. MOTIVOS DE ANULACIÓN
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga los motivos de anulación de facturas
     * (Error en factura, Devolución, etc.).
     */
    public function sincronizarMotivosAnulacion(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacion' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap()->call('sincronizarParametricaMotivoAnulacion', $params);
        $datos    = $this->extraerLista($response, 'RespuestaListaParametricas', 'listaCodigos', 'codigoClasificador');

        $this->guardar('motivos_anulacion', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 11. TIPOS DE PUNTO DE VENTA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga los tipos de punto de venta permitidos por el SIN.
     */
    public function sincronizarTiposPuntoVenta(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacion' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap()->call('sincronizarParametricaTipoPuntoVenta', $params);
        $datos    = $this->extraerLista($response, 'RespuestaListaParametricas', 'listaCodigos', 'codigoClasificador');

        $this->guardar('tipos_punto_venta', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 12. MENSAJES / CÓDIGOS DE ERROR DEL SIAT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga el catálogo de mensajes y códigos de error del SIAT.
     * Necesario para mostrar descripciones legibles de los errores.
     */
    public function sincronizarMensajesServicios(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacion' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap()->call('sincronizarListaMensajesServicios', $params);
        $datos    = $this->extraerLista($response, 'RespuestaListaParametricas', 'listaCodigos', 'descripcion');

        $this->guardar('mensajes_servicios', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 13. CLASIFICADOR DE PRODUCTOS Y SERVICIOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Descarga el clasificador de productos y servicios asociados a la actividad del contribuyente.
     */
    public function sincronizarProductosServicios(): array
    {
        $cuis = (new CodigosService($this->config))->obtenerCuis();

        $params = [
            'SolicitudSincronizacion' => [
                'codigoAmbiente'   => $this->config['ambiente'],
                'codigoModalidad'  => $this->config['modalidad'],
                'codigoSistema'    => $this->config['codigo_sistema'],
                'codigoSucursal'   => $this->config['sucursal'],
                'nit'              => $this->config['nit'],
                'cuis'             => $cuis,
            ],
        ];

        $response = $this->soap()->call('sincronizarListaProductosServicios', $params);
        $datos    = $this->extraerLista($response, 'RespuestaListaProductos', 'listaCodigos', 'codigoProducto');

        $this->guardar('productos_servicios', $datos);
        return $datos;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONSULTAS (Leer catálogos desde BD/caché)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Obtiene un catálogo por nombre. Primero busca en caché, luego en BD.
     *
     * @param string $catalogo  Nombre del catálogo (ej: 'metodos_pago')
     * @return array            Lista de ítems del catálogo
     * @throws SiatException    Si el catálogo no existe (necesita sincronizar)
     */
    public function obtener(string $catalogo): array
    {
        $cacheKey = "siat_catalogo_{$catalogo}_{$this->config['nit']}_{$this->config['ambiente']}";

        return Cache::remember($cacheKey, self::TTL_HORAS * 3600, function () use ($catalogo) {
            $registro = DB::table(self::TABLA)
                ->where('catalogo', $catalogo)
                ->where('nit', $this->config['nit'])
                ->where('ambiente', $this->config['ambiente'])
                ->first();

            if (!$registro) {
                throw new SiatException(
                    "Catálogo '{$catalogo}' no sincronizado. Ejecute: php artisan siat:sincronizar",
                    0, false
                );
            }

            return json_decode($registro->datos, true) ?? [];
        });
    }

    /**
     * Busca un ítem por código dentro de un catálogo.
     *
     * Ejemplo: obtenerPorCodigo('metodos_pago', 1) → ['codigo' => 1, 'descripcion' => 'Efectivo']
     */
    public function obtenerPorCodigo(string $catalogo, int|string $codigo): ?array
    {
        $lista = $this->obtener($catalogo);
        foreach ($lista as $item) {
            if (isset($item['codigo']) && (string)$item['codigo'] === (string)$codigo) {
                return $item;
            }
            if (isset($item['codigoActividad']) && (string)$item['codigoActividad'] === (string)$codigo) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Retorna la leyenda correspondiente a una actividad económica.
     * El SIN exige una leyenda aleatoria (o la primera) de la lista de la actividad.
     */
    public function obtenerLeyenda(string $codigoActividad): string
    {
        try {
            $leyendas = $this->obtener('leyendas');
            $filtradas = array_filter($leyendas, fn($l) =>
                isset($l['actividadEconomica']) && $l['actividadEconomica'] === $codigoActividad
            );

            if (!empty($filtradas)) {
                return array_values($filtradas)[0]['descripcion']
                    ?? 'El proveedor debe entregar factura al consumidor final.';
            }
        } catch (\Throwable) {
            // Si no hay catálogo, usamos leyenda por defecto
        }

        return 'Ley N° 453: El proveedor de servicios debe prioritariamente informar sobre las características de los productos.';
    }

    /**
     * Verifica si todos los catálogos obligatorios están sincronizados.
     *
     * @return array ['listo' => bool, 'faltantes' => array]
     */
    public function verificarEstado(): array
    {
        $obligatorios = [
            'actividades_economicas',
            'tipos_documento_identidad',
            'metodos_pago',
            'unidades_medida',
            'leyendas',
            'tipos_documento_sector',
            'monedas',
            'motivos_anulacion',
        ];

        $faltantes = [];

        foreach ($obligatorios as $catalogo) {
            $existe = DB::table(self::TABLA)
                ->where('catalogo', $catalogo)
                ->where('nit', $this->config['nit'])
                ->where('ambiente', $this->config['ambiente'])
                ->exists();

            if (!$existe) {
                $faltantes[] = $catalogo;
            }
        }

        return [
            'listo'     => empty($faltantes),
            'faltantes' => $faltantes,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Guarda o actualiza un catálogo en la BD y limpia el caché.
     */
    protected function guardar(string $catalogo, array $datos): void
    {
        $cacheKey = "siat_catalogo_{$catalogo}_{$this->config['nit']}_{$this->config['ambiente']}";

        DB::table(self::TABLA)->upsert(
            [
                'catalogo'       => $catalogo,
                'nit'            => $this->config['nit'],
                'ambiente'       => $this->config['ambiente'],
                'datos'          => json_encode($datos, JSON_UNESCAPED_UNICODE),
                'sincronizado_at'=> now(),
            ],
            uniqueBy: ['catalogo', 'nit', 'ambiente'],
            update:   ['datos', 'sincronizado_at']
        );

        // Invalidar caché para que el próximo obtener() traiga datos frescos
        Cache::forget($cacheKey);
    }

    /**
     * Extrae y normaliza una lista de la respuesta SOAP del SIAT.
     * Las respuestas SOAP pueden venir como array de arrays o array simple.
     */
    protected function extraerLista(
        array  $response,
        string $nodoRaiz,
        string $nodoLista,
        string $nodoItem
    ): array {
        // En SIAT V2, a veces el nodo raíz cambia pero la lista mantiene su nombre
        $raiz  = $response[$nodoRaiz] ?? array_shift($response);
        $lista = is_array($raiz) ? ($raiz[$nodoLista] ?? []) : [];

        if (empty($lista)) {
            return [];
        }

        $items = $lista[$nodoItem] ?? $lista;

        // Si viene un solo ítem (no array de arrays), lo envolvemos
        if (isset($items['codigoClasificador']) || isset($items['descripcion']) || isset($items['codigoActividad'])) {
            $items = [$items];
        }

        return array_values((array) $items);
    }
}

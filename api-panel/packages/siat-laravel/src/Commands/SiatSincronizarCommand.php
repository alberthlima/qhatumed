<?php

namespace Misalud\Siat\Commands;

use Illuminate\Console\Command;
use Misalud\Siat\Services\SincronizacionService;
use Misalud\Siat\Exceptions\SiatException;

/**
 * php artisan siat:sincronizar
 *
 * Descarga y almacena todos los catálogos paramétricos del SIAT.
 * Debe ejecutarse:
 *   1. Al instalar el SDK por primera vez
 *   2. Cuando el SIN actualice sus catálogos (raramente)
 *   3. Como tarea programada (schedule) cada 30 días
 *
 * Opciones:
 *   --solo=metodos_pago       Sincronizar solo un catálogo específico
 *   --verificar               Solo verificar qué catálogos faltan
 *   --forzar                  Forzar sincronización aunque estén actualizados
 */
class SiatSincronizarCommand extends Command
{
    protected $signature = 'siat:sincronizar
                            {--solo= : Nombre del catálogo a sincronizar (ej: metodos_pago)}
                            {--verificar : Solo verificar cuáles catálogos faltan, sin sincronizar}
                            {--forzar : Forzar resincronización aunque los datos estén actualizados}';

    protected $description = 'Sincroniza los catálogos paramétricos del SIAT (Impuestos Nacionales Bolivia)';

    // Lista de catálogos individuales disponibles con sus métodos
    protected array $catalogos = [
        'actividades_economicas'    => 'sincronizarActividades',
        'tipos_documento_identidad' => 'sincronizarTiposDocumentoIdentidad',
        'metodos_pago'              => 'sincronizarMetodosPago',
        'unidades_medida'           => 'sincronizarUnidadesMedida',
        'leyendas'                  => 'sincronizarLeyendas',
        'tipos_documento_sector'    => 'sincronizarTiposDocumentoSector',
        'monedas'                   => 'sincronizarMonedas',
        'paises'                    => 'sincronizarPaises',
        'municipios'                => 'sincronizarMunicipios',
        'motivos_anulacion'         => 'sincronizarMotivosAnulacion',
        'tipos_punto_venta'         => 'sincronizarTiposPuntoVenta',
        'mensajes_servicios'        => 'sincronizarMensajesServicios',
        'productos_servicios'       => 'sincronizarProductosServicios',
    ];

    // Resuelto de forma lazy en handle() para evitar SOAP en tiempo de boot
    protected SincronizacionService $svc;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Resolución lazy: solo se instancia (y conecta SOAP) cuando se ejecuta el comando
        $this->svc = app(SincronizacionService::class);

        $this->logo();

        // ── MODO VERIFICAR ──────────────────────────────────────────────────
        if ($this->option('verificar')) {
            return $this->verificarEstado();
        }

        // ── MODO CATÁLOGO ESPECÍFICO ────────────────────────────────────────
        $solo = $this->option('solo');
        if ($solo) {
            return $this->sincronizarUno($solo);
        }

        // ── MODO COMPLETO ───────────────────────────────────────────────────
        return $this->sincronizarTodo();
    }

    // ─────────────────────────────────────────────────────────────────────────

    protected function verificarEstado(): int
    {
        $estado = $this->svc->verificarEstado();

        if ($estado['listo']) {
            $this->info('✅ Todos los catálogos obligatorios están sincronizados.');
        } else {
            $this->warn('⚠️  Catálogos faltantes:');
            foreach ($estado['faltantes'] as $faltante) {
                $this->line("   • <comment>{$faltante}</comment>");
            }
            $this->newLine();
            $this->line('Ejecuta: <info>php artisan siat:sincronizar</info>');
        }

        return self::SUCCESS;
    }

    protected function sincronizarUno(string $catalogo): int
    {
        if (!isset($this->catalogos[$catalogo])) {
            $this->error("Catálogo desconocido: '{$catalogo}'");
            $this->line('Catálogos disponibles: ' . implode(', ', array_keys($this->catalogos)));
            return self::FAILURE;
        }

        $metodo = $this->catalogos[$catalogo];
        $this->line("Sincronizando <info>{$catalogo}</info>...");

        try {
            $datos = $this->svc->$metodo();
            $this->info("  ✅ {$catalogo}: " . count($datos) . ' ítems');
        } catch (\Throwable $e) {
            $this->error("  ❌ {$catalogo}: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function sincronizarTodo(): int
    {
        $this->info('Sincronizando catálogos con el SIAT...');
        $this->newLine();

        $resultados = $this->svc->sincronizarTodo();

        $exitosos  = 0;
        $fallidos  = 0;
        $filas     = [];

        foreach ($resultados as $catalogo => $resultado) {
            if ($resultado['ok']) {
                $filas[] = [
                    "<info>✅ {$catalogo}</info>",
                    $resultado['items'] . ' ítems',
                    '<info>OK</info>',
                ];
                $exitosos++;
            } else {
                $filas[] = [
                    "<error>❌ {$catalogo}</error>",
                    '—',
                    "<comment>{$resultado['error']}</comment>",
                ];
                $fallidos++;
            }
        }

        $this->table(['Catálogo', 'Ítems', 'Estado'], $filas);
        $this->newLine();

        if ($fallidos === 0) {
            $this->info("🎉 Sincronización completa: {$exitosos} catálogos actualizados.");
            return self::SUCCESS;
        }

        if ($exitosos === 0) {
            $this->error("❌ Sincronización fallida. Verifica la conexión y el Token SIAT.");
            $this->line('Revisa que tu .env tenga: SIAT_NIT, SIAT_TOKEN, SIAT_CODIGO_SISTEMA');
            return self::FAILURE;
        }

        $this->warn("⚠️  Sincronización parcial: {$exitosos} OK, {$fallidos} fallidos.");
        return self::FAILURE;
    }

    protected function logo(): void
    {
        $env = config('siat.ambiente') == 1 ? '🟢 PRODUCCIÓN' : '🔵 PILOTO/PRUEBAS';
        $this->newLine();
        $this->line("  <fg=cyan;options=bold>  SIAT Bolivia — Sincronización de Catálogos</>");
        $this->line("  NIT: <info>" . config('siat.nit') . "</info>  |  Ambiente: <info>{$env}</info>");
        $this->newLine();
    }
}

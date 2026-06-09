<?php

namespace Misalud\Siat\Commands;

use Illuminate\Console\Command;
use Misalud\Siat\Facades\Siat;
use Misalud\Siat\Services\CodigosService;

class SiatVerificarComunicacionCommand extends Command
{
    protected $signature = 'siat:comunicacion';
    protected $description = 'Verifica la comunicación básica con el SIAT (Heartbeat)';

    public function handle()
    {
        $this->info("\n    SIAT Bolivia — Verificación de Comunicación");
        $this->line("  Ambiente: " . (config('siat.ambiente') == 1 ? '🔴 PRODUCCIÓN' : '🔵 PILOTO/PRUEBAS') . "\n");

        try {
            $service = new CodigosService(config('siat'));
            $success = $service->verificarComunicacion();

            if ($success) {
                $this->info("✅ Conexión establecida exitosamente con el SIAT.");
            } else {
                $this->error("❌ El SIAT respondió, pero la transacción no fue exitosa.");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error de comunicación: " . $e->getMessage());
        }

        return 0;
    }
}

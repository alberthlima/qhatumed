<?php

namespace Misalud\Siat;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Misalud\Siat\Commands\SiatSincronizarCommand;
use Misalud\Siat\Commands\SiatVerificarComunicacionCommand;
use Misalud\Siat\Services\SincronizacionService;

class SiatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mergeamos la config del package con la del proyecto
        $this->mergeConfigFrom(
            __DIR__ . '/../config/siat.php',
            'siat'
        );

        // Registrar el SiatClient como singleton en el container Laravel
        $this->app->singleton(SiatClient::class, function ($app) {
            return new SiatClient($app['config']['siat']);
        });

        // Registrar SincronizacionService como singleton
        $this->app->singleton(SincronizacionService::class, function ($app) {
            return new SincronizacionService($app['config']['siat']);
        });

        // Alias 'siat' para el Facade
        $this->app->alias(SiatClient::class, 'siat');
    }

    public function boot(): void
    {
        // Sobreescribir configuración global siat desde la base de datos (CompanySetting)
        try {
            if (\Schema::hasTable('company_settings')) {
                $setting = \DB::table('company_settings')->first();
                if ($setting) {
                    $config = config('siat');
                    if (!empty($setting->legal_name)) $config['razon_social'] = $setting->legal_name;
                    if (!empty($setting->nit)) $config['nit'] = $setting->nit;
                    if (!empty($setting->siat_token)) $config['token'] = $setting->siat_token;
                    if (!empty($setting->siat_system_code)) $config['codigo_sistema'] = $setting->siat_system_code;
                    if (!empty($setting->siat_environment)) $config['ambiente'] = $setting->siat_environment;
                    if (!empty($setting->siat_modality)) $config['modalidad'] = $setting->siat_modality;
                    if ($setting->siat_branch_code !== null) $config['sucursal'] = $setting->siat_branch_code;
                    if ($setting->siat_pos_code !== null) $config['punto_venta'] = $setting->siat_pos_code;
                    config(['siat' => $config]);
                }
            }
        } catch (\Throwable $e) {
            // Evitar fallas si la BD no está migrada aún
        }

        // Cargar migraciones automáticamente en cualquier contexto
        // (necesario para que php artisan migrate las detecte)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Registrar comandos Artisan (disponibles tanto en consola como desde llamadas web)
        $this->commands([
            SiatSincronizarCommand::class,
            SiatVerificarComunicacionCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            // Publicar config
            $this->publishes([
                __DIR__ . '/../config/siat.php' => config_path('siat.php'),
            ], 'siat-config');

            // Publicar migraciones (opcional, ya se cargan automáticamente)
            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'siat-migrations');
        }
    }

    public function provides(): array
    {
        return [SiatClient::class, SincronizacionService::class, 'siat'];
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de almacenamiento de catálogos paramétricos del SIAT.
 *
 * Diseño: Una fila por catálogo + NIT + ambiente.
 * Los datos se guardan como JSON en la columna 'datos'.
 * Uso una única tabla en lugar de tablas por catálogo para:
 *   - Portabilidad entre proyectos
 *   - Evitar decenas de migraciones
 *   - Facilitar listar/borrar todos los catálogos de una vez
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siat_catalogos', function (Blueprint $table) {
            $table->id();

            // Nombre del catálogo (ej: 'metodos_pago', 'actividades_economicas')
            $table->string('catalogo', 60);

            // NIT del contribuyente propietario de estos catálogos
            // (permite multi-tenant: varios NITs en el mismo sistema)
            $table->string('nit', 15);

            // 1 = Producción, 2 = Pruebas/Piloto
            $table->tinyInteger('ambiente')->default(2);

            // Los datos del catálogo serializados como JSON
            $table->longText('datos');

            // Fecha de última sincronización (para saber si hay que actualizar)
            $table->timestamp('sincronizado_at')->nullable();

            // Clave única: un catalogo por NIT por ambiente
            $table->unique(['catalogo', 'nit', 'ambiente'], 'siat_catalogos_unique');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siat_catalogos');
    }
};

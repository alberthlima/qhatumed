<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siat_eventos_contingencia', function (Blueprint $table) {
            $table->id();
            $table->integer('codigo_motivo');
            $table->string('descripcion', 255);
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');
            $table->string('codigo_recepcion_evento', 100);
            $table->string('codigo_recepcion_paquete', 100)->nullable();
            $table->enum('estado_transmision', ['PENDIENTE', 'TRANSMITIDO', 'VALIDADO', 'RECHAZADA'])->default('PENDIENTE');
            $table->text('detalles_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siat_eventos_contingencia');
    }
};

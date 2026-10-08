<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos normativos para Facturación Fuera de Línea / Contingencia (RND 102100000011):
     * - codigo_emision: 1 = En Línea (Online), 2 = Fuera de Línea (Offline / Contingencia), 3 = Masiva
     * - estado_siat: ONLINE_VALIDADA, OFFLINE_PENDIENTE, OFFLINE_TRANSMITIDO
     * - evento_contingencia_id: Vínculo con el Evento Significativo registrado ante el SIN
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->tinyInteger('codigo_emision')->default(1)->after('document_type_code');
            $table->string('estado_siat', 30)->default('ONLINE_VALIDADA')->after('codigo_emision');
            $table->foreignId('evento_contingencia_id')
                ->nullable()
                ->after('estado_siat')
                ->constrained('siat_eventos_contingencia')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['evento_contingencia_id']);
            $table->dropColumn(['codigo_emision', 'estado_siat', 'evento_contingencia_id']);
        });
    }
};

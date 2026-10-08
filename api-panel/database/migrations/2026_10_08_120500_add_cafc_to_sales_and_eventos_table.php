<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('cafc', 50)->nullable()->after('codigo_emision');
        });

        Schema::table('siat_eventos_contingencia', function (Blueprint $table) {
            $table->string('cafc', 50)->nullable()->after('codigo_recepcion_evento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('cafc');
        });

        Schema::table('siat_eventos_contingencia', function (Blueprint $table) {
            $table->dropColumn('cafc');
        });
    }
};

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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            
            // Company Info
            $table->string('trade_name')->nullable(); // Nombre Comercial
            $table->string('legal_name')->nullable(); // Razón Social
            $table->string('nit')->nullable();
            
            // SIAT Config
            $table->integer('siat_environment')->default(2); // 1: Prod, 2: Piloto
            $table->integer('siat_modality')->default(2); // 1: Electrónica, 2: Computarizada
            $table->text('siat_token')->nullable();
            $table->string('siat_system_code')->nullable();
            $table->integer('siat_branch_code')->default(0);
            $table->integer('siat_pos_code')->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};

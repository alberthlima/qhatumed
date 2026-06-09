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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('user_id')->constrained('users'); // Quien vendió
            
            $table->dateTime('sale_date');
            
            // Datos Denormalizados para Facturación (SIAT o Recibo simple)
            $table->string('business_name', 150); // Razón social de ese momento
            $table->string('nit_ci', 50);
            
            // SIAT BOLIVIA Campos
            $table->string('invoice_number', 50)->nullable(); // Número de factura SIAT
            $table->string('cuf', 100)->nullable(); // Código Único de Factura
            $table->string('cufd', 100)->nullable(); // Código de Recepción
            $table->string('siat_uid', 100)->nullable(); // ID Recepción paquete SIAT
            $table->string('invoice_url', 255)->nullable(); // Link de la factura PDF
            
            $table->decimal('total_amount', 11, 2);
            $table->decimal('cash_tendered', 11, 2)->default(0); // Efectivo dado
            $table->decimal('change', 11, 2)->default(0); // Cambio devuelto
            
            $table->string('status', 20)->default('COMPLETED'); // PENDING, COMPLETED, ANNULLED (Para poder anular Factura en SIAT)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};

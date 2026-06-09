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
        // Campos en PRODUCTOS para mapeo SIAT
        Schema::table('products', function (Blueprint $table) {
            $table->string('siat_product_code', 20)->default('99100')->after('barcode'); // 99100: Medicamentos genérico
            $table->integer('siat_unit_measure_code')->default(57)->after('siat_product_code'); // 57: Unidad
        });

        // Campos en VENTAS para parámetros fiscales
        Schema::table('sales', function (Blueprint $table) {
            $table->integer('payment_method_code')->default(1)->after('nit_ci'); // 1: Efectivo
            $table->integer('document_type_code')->default(1)->after('payment_method_code'); // 1: CI
            $table->boolean('is_invoice')->default(true)->after('document_type_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['siat_product_code', 'siat_unit_measure_code']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['payment_method_code', 'document_type_code', 'is_invoice']);
        });
    }
};

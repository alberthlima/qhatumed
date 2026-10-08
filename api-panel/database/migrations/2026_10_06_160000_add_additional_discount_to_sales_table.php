<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Descuento adicional global (SIAT: descuentoAdicional).
     * Antes se enviaba desde el POS pero se descartaba en el backend, por lo que
     * nunca llegaba a la factura.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('additional_discount', 11, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('additional_discount');
        });
    }
};

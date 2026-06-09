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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('dispenser')->nullable()->comment('Tipo de empaque para venta detallada, ej: Caja');
            $table->integer('dispenser_quantity')->nullable()->default(1)->comment('Cantidad de unidades que trae el dispenser');
            $table->string('therapeutic_action')->nullable();
            $table->string('active_ingredient')->nullable();
            $table->text('indications')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn([
                'supplier_id',
                'dispenser',
                'dispenser_quantity',
                'therapeutic_action',
                'active_ingredient',
                'indications'
            ]);
        });
    }
};

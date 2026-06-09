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
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            
            // Físico / FEFO
            $table->string('batch', 50)->nullable(); // Lote
            $table->date('expiration_date')->nullable(); // Fecha Vencimiento
            
            $table->integer('quantity');
            $table->integer('current_stock'); // Para descargar el FEFO, esto bajará cuando se venda de este lote
            
            $table->decimal('unit_cost', 11, 2); // Costo al que se compró, para recalcular el CPP al ingresar
            
            // Opcional para reportes de utilidades teóricas vs prácticas
            $table->decimal('suggested_sale_price', 11, 2)->nullable(); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};

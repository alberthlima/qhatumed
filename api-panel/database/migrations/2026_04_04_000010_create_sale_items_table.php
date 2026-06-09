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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products'); // Qué producto se vendió
            
            // FEFO Link: ¿De qué lote salió físicamente?
            // Podría ser nulable si deciden no controlar lotes para ciertos productos sueltos
            $table->foreignId('purchase_item_id')->nullable()->constrained('purchase_items'); 
            
            $table->integer('quantity');
            $table->decimal('unit_price', 11, 2); // Precio al que se vendió
            $table->decimal('discount', 11, 2)->default(0); // Descuento
            $table->decimal('subtotal', 11, 2);
            
            // ESTO ES MAGIA CONTABLE:
            // Al momento de vender, guardamos el CPP actual del producto.
            // Para calcular luego ganancia total: (unit_price * quantity) - (average_cost_at_sale * quantity)
            $table->decimal('average_cost_at_sale', 11, 2); 
            
            // Y por si quieren un reporte FEFO estricto, calculamos usando el costo del lote
            $table->decimal('batch_cost_at_sale', 11, 2)->nullable(); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};

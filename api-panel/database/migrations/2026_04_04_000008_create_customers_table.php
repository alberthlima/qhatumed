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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150); // Razón social o Nombre
            $table->foreignId('type_document_id')->nullable()->constrained('type_documents'); // CI, NIT, PASAPORTE (SIAT)
            $table->string('document_number', 50)->nullable(); // Número de CI o NIT (SIAT)
            $table->string('complement', 10)->nullable(); // Complemento para CIs duplicados (SIAT)
            $table->string('phone', 25)->nullable();
            $table->string('address', 200)->nullable();
            $table->string('email', 100)->nullable(); // Importante para mandar factura SIAT por correo
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

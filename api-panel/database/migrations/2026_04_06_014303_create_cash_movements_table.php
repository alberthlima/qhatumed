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
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_session_id')->constrained('cash_register_sessions');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('type', ['IN', 'OUT']);
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->default('EFECTIVO'); // EFECTIVO, QR, TRANSFERENCIA
            $table->string('description');
            
            // For polymorphic relation (nullable) e.g., mapping to a specific Sale ID
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};

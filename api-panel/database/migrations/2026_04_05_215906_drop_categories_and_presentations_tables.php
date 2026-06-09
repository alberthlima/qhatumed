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
            if (Schema::hasColumn('products', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
            if (Schema::hasColumn('products', 'presentation_id')) {
                $table->dropForeign(['presentation_id']);
                $table->dropColumn('presentation_id');
            }
        });

        Schema::dropIfExists('categories');
        Schema::dropIfExists('presentations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('presentations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->foreignId('presentation_id')->nullable()->constrained('presentations');
        });
    }
};

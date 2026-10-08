<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos para medios de pago exigidos en inspección de Sistema Tipo Proveedor:
     * - card_amount: Desglose del monto pagado con tarjeta en método combinado (Código 10)
     * - gift_card_amount: Monto cubierto por Gift Card (Código 27)
     * - gift_card_code: Número / Código identificador de la Gift Card o vale
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('card_amount', 11, 2)->nullable()->default(0)->after('card_number');
            $table->decimal('gift_card_amount', 11, 2)->nullable()->default(0)->after('card_amount');
            $table->string('gift_card_code', 50)->nullable()->after('gift_card_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['card_amount', 'gift_card_amount', 'gift_card_code']);
        });
    }
};

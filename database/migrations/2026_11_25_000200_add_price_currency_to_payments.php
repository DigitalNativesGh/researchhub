<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prices are set in US dollars and charged in cedis. Each payment keeps the dollar price the researcher saw and
 * the exchange rate used, so later rate changes never alter a payment that has already started. "amount" and
 * "currency" stay the charged (billing) amount that Paystack verifies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('price_amount')->nullable()->after('currency'); // minor units (cents)
            $table->string('price_currency', 3)->nullable()->after('price_amount');
            $table->decimal('exchange_rate', 12, 4)->nullable()->after('price_currency');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['price_amount', 'price_currency', 'exchange_rate']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per checkout attempt. A successful payment with no project yet is a credit for one project
        // of that research type; creating the project attaches it.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('degree_level');
            $table->unsignedBigInteger('amount'); // minor units (pesewas, kobo, cents)
            $table->string('currency', 3);
            $table->string('reference')->unique();
            $table->string('status')->default('pending'); // pending, success, failed, abandoned
            $table->string('channel')->nullable(); // card, mobile_money
            $table->string('card_type')->nullable(); // visa, mastercard, or the mobile money provider
            $table->string('last4', 4)->nullable();
            $table->string('gateway_response')->nullable();
            $table->unsignedBigInteger('paystack_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            // Set when the payment is used for a project; stays set if that project is later deleted.
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'degree_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

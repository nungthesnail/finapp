<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariffs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_days');
            $table->decimal('price_rub', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tariff_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tariff_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('yoomoney');
            $table->string('provider_payment_id')->nullable()->unique();
            $table->string('idempotence_key')->unique();
            $table->decimal('amount_rub', 12, 2);
            $table->string('currency', 8)->default('RUB');
            $table->string('status')->default('pending');
            $table->string('confirmation_url')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('entry_type');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('RUB');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_ledger');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tariffs');
    }
};


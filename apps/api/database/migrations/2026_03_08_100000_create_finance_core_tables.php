<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('user_category_defaults', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('income_category_id')->constrained('income_categories')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('currency', 8)->default('RUB');
            $table->decimal('balance', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->unsignedBigInteger('category_id');
            $table->decimal('amount', 14, 2);
            $table->text('description')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('recurring_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->unsignedBigInteger('category_id');
            $table->decimal('amount', 14, 2);
            $table->text('description')->nullable();
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->timestamp('next_run_at')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('budget_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('period_from');
            $table->date('period_to');
            $table->enum('goal_type', ['savings', 'target_balance'])->default('savings');
            $table->decimal('goal_amount', 14, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('budget_plan_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->unsignedBigInteger('category_id');
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('budget_amount', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plan_categories');
        Schema::dropIfExists('budget_plans');
        Schema::dropIfExists('recurring_transactions');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('user_category_defaults');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('income_categories');
    }
};


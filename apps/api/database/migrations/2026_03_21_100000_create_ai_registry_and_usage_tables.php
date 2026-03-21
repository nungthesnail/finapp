<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('provider', 64)->default('openai');
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_tools')->default(true);
            $table->decimal('input_cost_per_1k', 12, 6)->default(0);
            $table->decimal('output_cost_per_1k', 12, 6)->default(0);
            $table->decimal('cached_input_cost_per_1k', 12, 6)->default(0);
            $table->timestamps();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->string('model_code', 64);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cached_input_tokens')->default(0);
            $table->decimal('total_cost_rub', 12, 6)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        DB::table('ai_models')->insert([
            [
                'code' => 'gpt-4.1-mini',
                'name' => 'GPT-4.1 mini',
                'provider' => 'openai',
                'is_active' => true,
                'supports_tools' => true,
                'input_cost_per_1k' => 0.500000,
                'output_cost_per_1k' => 1.500000,
                'cached_input_cost_per_1k' => 0.100000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'gpt-5-mini',
                'name' => 'GPT-5 mini',
                'provider' => 'openai',
                'is_active' => true,
                'supports_tools' => true,
                'input_cost_per_1k' => 0.800000,
                'output_cost_per_1k' => 2.400000,
                'cached_input_cost_per_1k' => 0.200000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_models');
    }
};

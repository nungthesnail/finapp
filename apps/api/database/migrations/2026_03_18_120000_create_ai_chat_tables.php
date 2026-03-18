<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('New chat');
            $table->string('selected_model')->nullable();
            $table->boolean('is_technical')->default(false);
            $table->timestamp('last_active_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->longText('content');
            $table->string('model')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};


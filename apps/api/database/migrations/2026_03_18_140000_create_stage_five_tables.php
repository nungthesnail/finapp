<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64)->default('system');
            $table->string('title');
            $table->text('content')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('device_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint')->unique();
            $table->string('p256dh')->nullable();
            $table->string('auth')->nullable();
            $table->json('raw')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_chats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('open');
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_id')->constrained('support_chats')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('sender_role', 16); // USER | ADMIN
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 128);
            $table->string('entity_type', 64)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_chats');
        Schema::dropIfExists('device_subscriptions');
        Schema::dropIfExists('notifications');
    }
};


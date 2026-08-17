<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bot audience (spec §6 table 1). Telegram IDs are BIGINT.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tg_chat_id')->unique();
            $table->string('first_name');
            $table->string('username')->nullable();
            $table->string('language', 8)->nullable();
            $table->string('source')->nullable();
            $table->foreignId('referred_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('last_active_at')->nullable();
            $table->boolean('blocked_bot')->default(false);
            $table->timestamp('blocked_at')->nullable();
            $table->boolean('in_channel')->default(false);
            $table->timestamp('channel_checked_at')->nullable();
            $table->timestamps();

            $table->index('language');
            $table->index('source');
            $table->index('joined_at');
            $table->index('last_active_at');
            $table->index('blocked_bot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

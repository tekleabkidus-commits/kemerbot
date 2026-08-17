<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Inbound + admin 1:1 only — never broadcast copies (spec §6 table 18).
// Pruned after config('telegram.message_retention_days') by a scheduled job.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->bigInteger('tg_message_id')->nullable();
            $table->enum('direction', ['inbound', 'admin_outbound']);
            $table->string('type', 32);
            $table->text('text')->nullable();
            $table->jsonb('media_meta')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};

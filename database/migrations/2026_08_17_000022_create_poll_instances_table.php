<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Correlates each sent Telegram poll to its parent — documented exception to
// Principle 3 (spec §6 table 22). Minimal columns only. Telegram poll ids are
// strings in the Bot API, hence varchar.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tg_poll_id', 64)->unique();
            $table->timestamp('sent_at');

            $table->index('poll_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_instances');
    }
};

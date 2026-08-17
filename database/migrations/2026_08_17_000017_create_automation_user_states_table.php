<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Durable per-user execution state — documented exception to Principle 3
// (spec §6 table 17). Survives worker restarts; state lives in Postgres.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_user_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('current_step_no')->default(0);
            $table->enum('status', ['active', 'completed', 'cancelled', 'cooldown'])->default('active');
            $table->timestamp('triggered_at');
            $table->timestamp('last_step_sent_at')->nullable();
            $table->timestamp('next_step_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
            $table->string('trigger_key');
            $table->timestamps();

            // Leftmost prefix covers the (automation_id, user_id) index from spec §6.
            $table->unique(['automation_id', 'user_id', 'trigger_key']);
            $table->index(['status', 'next_step_at']);
            $table->index('cooldown_until');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_user_states');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HARDENING §4: transactional outbox for automation step sends. The runner
// claims a step by creating this row IN THE SAME TRANSACTION that parks the
// user state; the queue job owns the row through sending → sent/failed.
// unique(state, step) makes a step deliverable at most once per enrollment,
// whatever the application layer does.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_step_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_user_state_id')->constrained('automation_user_states')->cascadeOnDelete();
            $table->foreignId('automation_step_id')->constrained('automation_steps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['queued', 'sending', 'sent', 'failed'])->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->unique(['automation_user_state_id', 'automation_step_id']);
            // Recovery scans: stale queued/sending rows.
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_step_deliveries');
    }
};

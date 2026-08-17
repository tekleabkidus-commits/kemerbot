<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Campaigns (spec §6 table 9). Counters live here — never per-recipient success
// rows (Principle 3). The `sent+blocked+failed ≤ queued` invariant is enforced in
// BroadcastLifecycle + tests, not as a CHECK, because the documented resume edge
// (spec §7) permits one re-sent chunk after a worker death.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['standard', 'match_card', 'poll']);
            $table->enum('status', [
                'draft', 'scheduled', 'preparing', 'sending',
                'paused', 'completed', 'cancelled', 'failed',
            ])->default('draft');
            $table->jsonb('audience_filter')->nullable();
            $table->unsignedInteger('audience_snapshot_count')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->jsonb('recurrence')->nullable();
            $table->jsonb('template_fields')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->integer('queued')->default(0);
            $table->integer('sent')->default(0);
            $table->integer('blocked')->default(0);
            $table->integer('failed')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_at');
            $table->index('created_at');
        });

        DB::statement('ALTER TABLE broadcasts ADD CONSTRAINT broadcasts_counters_non_negative_check CHECK (queued >= 0 AND sent >= 0 AND blocked >= 0 AND failed >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};

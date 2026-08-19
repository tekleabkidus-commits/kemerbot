<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// HARDENING §6: recurring materialization gets database-level idempotency.
// A child occurrence is uniquely identified by (parent, occurrence_at); the
// unique index is the final defense even if application locking fails.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->foreignId('parent_broadcast_id')->nullable()->constrained('broadcasts')->nullOnDelete();
            $table->timestamp('occurrence_at')->nullable();
        });

        // Plain unique works: Postgres treats NULLs as distinct, so manual
        // duplicates (parent NULL) never collide.
        DB::statement('CREATE UNIQUE INDEX broadcasts_parent_occurrence_unique ON broadcasts (parent_broadcast_id, occurrence_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS broadcasts_parent_occurrence_unique');

        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_broadcast_id');
            $table->dropColumn('occurrence_at');
        });
    }
};

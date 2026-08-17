<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §5.5 requires per-step sent counters; table 15 omitted the column and
// Batch 1 followed the table. Column-level fix, no new table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_steps', function (Blueprint $table) {
            $table->unsignedBigInteger('sent_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('automation_steps', function (Blueprint $table) {
            $table->dropColumn('sent_count');
        });
    }
};

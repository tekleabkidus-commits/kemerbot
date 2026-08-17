<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Callback-button analytics — documented exception to Principle 3 (spec §6 table 19).
// Append-only: clicked_at is the row's timestamp.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('button_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->foreignId('button_id')->constrained('broadcast_buttons')->cascadeOnDelete();
            $table->timestamp('clicked_at');

            $table->index('broadcast_id');
            $table->index('user_id');
            $table->index('clicked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('button_clicks');
    }
};

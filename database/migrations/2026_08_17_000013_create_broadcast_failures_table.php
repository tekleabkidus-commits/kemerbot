<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Failures only — never successful deliveries (spec §6 table 13, Principle 3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('tg_error_code')->nullable();
            $table->enum('category', ['blocked', 'rate', 'network', 'invalid', 'other']);
            $table->string('sanitized_error', 512)->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('first_failed_at');
            $table->timestamp('last_failed_at');
            $table->timestamps();

            // Also serves as the broadcast_id index (leftmost prefix).
            $table->unique(['broadcast_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_failures');
    }
};

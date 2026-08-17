<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Parent poll (spec §6 table 21). question/options are jsonb language maps
// (spec §4.12); answer_counts aggregates per option index.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->jsonb('question');
            $table->jsonb('options');
            $table->jsonb('answer_counts')->nullable();
            $table->boolean('is_anonymous')->default(true);
            $table->timestamps();

            // One poll definition per poll-type broadcast.
            $table->unique('broadcast_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §6 table 16, §4.12.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_step_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_step_id')->constrained('automation_steps')->cascadeOnDelete();
            $table->enum('lang', ['en', 'am']);
            $table->text('text');
            $table->timestamps();

            $table->unique(['automation_step_id', 'lang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_step_translations');
    }
};

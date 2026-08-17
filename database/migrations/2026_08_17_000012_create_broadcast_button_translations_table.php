<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §6 table 12, §4.12.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_button_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_button_id')->constrained('broadcast_buttons')->cascadeOnDelete();
            $table->enum('lang', ['en', 'am']);
            $table->string('label');
            $table->timestamps();

            $table->unique(['broadcast_button_id', 'lang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_button_translations');
    }
};

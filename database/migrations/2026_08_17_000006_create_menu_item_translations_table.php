<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §6 table 6, §4.12.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->enum('lang', ['en', 'am']);
            $table->string('label');
            $table->text('reply_text')->nullable();
            $table->timestamps();

            $table->unique(['menu_item_id', 'lang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_translations');
    }
};

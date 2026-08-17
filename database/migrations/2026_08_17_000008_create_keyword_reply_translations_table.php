<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §6 table 8, §4.12.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_reply_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_reply_id')->constrained('keyword_replies')->cascadeOnDelete();
            $table->enum('lang', ['en', 'am']);
            $table->text('reply_text');
            $table->timestamps();

            $table->unique(['keyword_reply_id', 'lang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_reply_translations');
    }
};

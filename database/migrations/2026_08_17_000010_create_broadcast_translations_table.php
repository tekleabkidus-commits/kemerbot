<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §6 table 10, §4.12. Text nullable: poll broadcasts carry content in `polls`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->enum('lang', ['en', 'am']);
            $table->text('text')->nullable();
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->timestamps();

            $table->unique(['broadcast_id', 'lang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_translations');
    }
};

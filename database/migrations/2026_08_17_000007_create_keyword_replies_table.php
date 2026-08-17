<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Auto-replies (spec §6 table 7). `buttons` jsonb added because §5.1 allows optional
// buttons on keyword replies; same embedded shape as automation_steps.buttons.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_replies', function (Blueprint $table) {
            $table->id();
            $table->jsonb('keywords');
            $table->enum('match_type', ['exact', 'contains']);
            $table->integer('position')->default(0);
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->jsonb('buttons')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_replies');
    }
};

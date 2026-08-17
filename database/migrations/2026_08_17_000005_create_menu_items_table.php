<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Menu tree (spec §6 table 5).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->integer('position')->default(0);
            $table->enum('action_type', ['reply', 'submenu', 'url', 'webapp']);
            $table->string('url', 2048)->nullable();
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};

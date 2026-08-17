<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §6 table 11.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_buttons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->integer('row')->default(0);
            $table->integer('position')->default(0);
            $table->enum('kind', ['url', 'callback']);
            $table->string('url', 2048)->nullable();
            $table->timestamps();

            $table->index('broadcast_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_buttons');
    }
};

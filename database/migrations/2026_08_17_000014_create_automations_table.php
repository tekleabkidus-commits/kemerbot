<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §6 table 14. Triggers are user_joined/inactive only (spec §4.3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('trigger', ['user_joined', 'inactive']);
            $table->jsonb('trigger_config')->nullable();
            $table->unsignedInteger('cooldown_days')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['trigger', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};

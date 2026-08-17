<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Spec §6 table 15. delay_hours = delay since previous step (spec §4.4).
// buttons: jsonb rows of {kind, url, label: {en, am}}.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->unsignedInteger('step_no');
            $table->unsignedInteger('delay_hours');
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->jsonb('buttons')->nullable();
            $table->timestamps();

            $table->unique(['automation_id', 'step_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_steps');
    }
};

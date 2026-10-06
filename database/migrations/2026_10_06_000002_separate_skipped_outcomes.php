<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', fn (Blueprint $table) => $table->unsignedInteger('skipped')->default(0));
    }

    public function down(): void
    {
        Schema::table('broadcasts', fn (Blueprint $table) => $table->dropColumn('skipped'));
    }
};

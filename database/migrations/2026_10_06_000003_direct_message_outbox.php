<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_message_deliveries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $t->text('text');
            $t->string('status')->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at')->nullable();
            $t->timestamp('claimed_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_message_deliveries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('marketing_subscribed')->default(true)->index();
            $t->string('preferred_language', 8)->nullable();
            $t->jsonb('topics')->nullable();
            $t->unsignedSmallInteger('daily_message_limit')->default(3);
            $t->string('support_status')->default('closed')->index();
            $t->foreignId('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $t->text('support_note')->nullable();
            $t->timestamp('converted_at')->nullable()->index();
        });
        Schema::table('admins', fn (Blueprint $t) => $t->text('app_authentication_secret')->nullable());
        Schema::table('broadcasts', function (Blueprint $t) {
            $t->string('name')->nullable();
            $t->string('topic')->default('general');
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('snapshot_built_at')->nullable();
            $t->jsonb('experiment')->nullable();
            $t->text('failure_reason')->nullable();
        });
        Schema::table('poll_instances', function (Blueprint $t) {
            $t->jsonb('previous_counts')->nullable();
            $t->bigInteger('last_update_id')->nullable();
        });
        Schema::create('broadcast_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('broadcast_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->bigInteger('chat_id');
            $t->string('status')->default('ready');
            $t->unsignedInteger('attempt')->default(1);
            $t->string('variant')->default('a');
            $t->string('claim_token')->nullable();
            $t->timestamp('claimed_at')->nullable();
            $t->timestamp('available_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->unique(['broadcast_id', 'user_id']);
            $t->index(['broadcast_id', 'status', 'available_at']);
            $t->index(['user_id', 'sent_at']);
        });
        Schema::create('telegram_updates', function (Blueprint $t) {
            $t->id();
            $t->bigInteger('update_id')->unique();
            $t->jsonb('payload');
            $t->string('status')->default('pending');
            $t->timestamp('claimed_at')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamps();
            $t->index(['status', 'updated_at']);
        });
        Schema::create('audience_segments', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->jsonb('filter');
            $t->timestamps();
        });
        Schema::create('contact_send_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('delivery_key')->unique();
            $t->timestamp('reserved_at')->index();
            $t->timestamps();
            $t->index(['user_id', 'reserved_at']);
        });
        Schema::create('conversion_events', function (Blueprint $t) {
            $t->id();
            $t->string('external_id')->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('broadcast_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event');
            $t->decimal('value', 16, 2)->default(0);
            $t->string('currency', 3)->default('ETB');
            $t->timestamp('occurred_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['conversion_events', 'contact_send_slots', 'audience_segments', 'telegram_updates', 'broadcast_recipients'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('poll_instances', fn (Blueprint $t) => $t->dropColumn(['previous_counts', 'last_update_id']));
        Schema::table('broadcasts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('approved_by');
            $t->dropColumn(['name', 'topic', 'expires_at', 'approved_at', 'snapshot_built_at', 'experiment', 'failure_reason']);
        });
        Schema::table('admins', fn (Blueprint $t) => $t->dropColumn('app_authentication_secret'));
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('assigned_admin_id');
            $t->dropColumn(['marketing_subscribed', 'preferred_language', 'topics', 'daily_message_limit', 'support_status', 'support_note', 'converted_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX users_support_queue_idx ON users (last_active_at DESC) WHERE support_status <> 'closed'");
        DB::statement('CREATE INDEX users_subscribed_language_idx ON users (preferred_language, language) WHERE marketing_subscribed = true AND blocked_bot = false');
        DB::statement('CREATE INDEX conversions_campaign_user_idx ON conversion_events (broadcast_id, user_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_support_queue_idx');
        DB::statement('DROP INDEX IF EXISTS users_subscribed_language_idx');
        DB::statement('DROP INDEX IF EXISTS conversions_campaign_user_idx');
    }
};

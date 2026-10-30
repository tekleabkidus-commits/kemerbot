<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL uses a CHECK constraint for Laravel's enum columns. Expand it
 * to allow the new any-message fallback while preserving existing rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE keyword_replies DROP CONSTRAINT IF EXISTS keyword_replies_match_type_check');
        DB::statement("ALTER TABLE keyword_replies ADD CONSTRAINT keyword_replies_match_type_check CHECK (match_type IN ('exact', 'contains', 'any'))");
    }

    public function down(): void
    {
        // Never silently delete existing catch-all rules on rollback.
        if (DB::table('keyword_replies')->where('match_type', 'any')->exists()) {
            throw new RuntimeException('Disable or migrate Any message rules before rolling back.');
        }

        DB::statement('ALTER TABLE keyword_replies DROP CONSTRAINT IF EXISTS keyword_replies_match_type_check');
        DB::statement("ALTER TABLE keyword_replies ADD CONSTRAINT keyword_replies_match_type_check CHECK (match_type IN ('exact', 'contains'))");
    }
};

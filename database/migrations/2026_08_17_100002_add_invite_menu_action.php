<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Spec §5.6: "Invite friends" is a MENU ACTION. The action_type check
// constraint gains 'invite'; constraint-level change only, no new table.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE menu_items DROP CONSTRAINT menu_items_action_type_check');
        DB::statement("ALTER TABLE menu_items ADD CONSTRAINT menu_items_action_type_check CHECK (action_type::text = ANY (ARRAY['reply'::character varying, 'submenu'::character varying, 'url'::character varying, 'webapp'::character varying, 'invite'::character varying]::text[]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE menu_items DROP CONSTRAINT menu_items_action_type_check');
        DB::statement("ALTER TABLE menu_items ADD CONSTRAINT menu_items_action_type_check CHECK (action_type::text = ANY (ARRAY['reply'::character varying, 'submenu'::character varying, 'url'::character varying, 'webapp'::character varying]::text[]))");
    }
};

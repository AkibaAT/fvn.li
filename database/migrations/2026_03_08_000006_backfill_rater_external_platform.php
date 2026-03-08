<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('raters')
            ->whereNull('external_platform')
            ->whereNotNull('itch_id')
            ->update(['external_platform' => 'itch_io']);
    }

    public function down(): void
    {
        // Not reversible — we don't know which ones were originally null
    }
};

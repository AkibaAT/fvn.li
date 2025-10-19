<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * The original raters table was designed for fvn.li users only, where each user
     * has exactly one rater profile (hence the unique constraint on user_id).
     * 
     * With multi-platform support (Steam, etc.), we need to allow multiple raters
     * to be linked to the same system user (e.g., all Steam raters linked to a
     * "Steam Reviews Bot" user). This migration removes the unique constraint.
     */
    public function up(): void
    {
        Schema::table('raters', function (Blueprint $table) {
            $table->dropUnique('raters_user_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raters', function (Blueprint $table) {
            $table->unique('user_id', 'raters_user_id_unique');
        });
    }
};


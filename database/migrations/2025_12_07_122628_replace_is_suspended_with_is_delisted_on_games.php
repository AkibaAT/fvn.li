<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Add is_delisted column with default false
            $table->boolean('is_delisted')->default(false)->after('has_demo');
        });

        Schema::table('games', function (Blueprint $table) {
            // Drop is_suspended column
            $table->dropColumn('is_suspended');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Re-add is_suspended column
            $table->boolean('is_suspended')->nullable()->after('has_demo');
        });

        Schema::table('games', function (Blueprint $table) {
            // Drop is_delisted column
            $table->dropColumn('is_delisted');
        });
    }
};

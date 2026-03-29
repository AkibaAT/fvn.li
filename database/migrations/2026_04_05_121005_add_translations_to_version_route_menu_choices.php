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
        Schema::table('version_route_menu_choices', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('text');
            $table->json('prompt_translations')->nullable()->after('prompt');
        });
    }

    public function down(): void
    {
        Schema::table('version_route_menu_choices', function (Blueprint $table) {
            $table->dropColumn(['translations', 'prompt_translations']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_route_menu_choices', function (Blueprint $table) {
            $table->json('menu_condition_stack')->nullable()->after('menu_branch');
        });
    }

    public function down(): void
    {
        Schema::table('version_route_menu_choices', function (Blueprint $table) {
            $table->dropColumn('menu_condition_stack');
        });
    }
};

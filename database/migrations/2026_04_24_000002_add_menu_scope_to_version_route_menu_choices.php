<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_route_menu_choices', function (Blueprint $table) {
            $table->text('menu_branch')->nullable()->after('choice_condition');
            $table->integer('parent_menu_line')->default(0)->after('menu_branch');
            $table->integer('parent_choice_line')->default(0)->after('parent_menu_line');
            $table->index(['game_version_id', 'from_label', 'menu_branch'], 'vrmc_version_label_menu_branch_index');
        });
    }

    public function down(): void
    {
        Schema::table('version_route_menu_choices', function (Blueprint $table) {
            $table->dropIndex('vrmc_version_label_menu_branch_index');
            $table->dropColumn(['menu_branch', 'parent_menu_line', 'parent_choice_line']);
        });
    }
};

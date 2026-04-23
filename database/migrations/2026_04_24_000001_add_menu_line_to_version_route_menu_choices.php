<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_route_menu_choices', function (Blueprint $table) {
            $table->integer('menu_line')->default(0)->after('prompt_translations');
            $table->text('enclosing_condition')->nullable()->after('condition');
            $table->text('choice_condition')->nullable()->after('enclosing_condition');
            $table->index(['game_version_id', 'from_label', 'menu_line']);
        });
    }

    public function down(): void
    {
        Schema::table('version_route_menu_choices', function (Blueprint $table) {
            $table->dropIndex(['game_version_id', 'from_label', 'menu_line']);
            $table->dropColumn(['menu_line', 'enclosing_condition', 'choice_condition']);
        });
    }
};

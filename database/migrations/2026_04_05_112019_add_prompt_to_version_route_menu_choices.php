<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_route_menu_choices', function (Blueprint $table) {
            $table->text('prompt')->nullable()->after('from_label');
        });
    }

    public function down(): void
    {
        Schema::table('version_route_menu_choices', function (Blueprint $table) {
            $table->dropColumn('prompt');
        });
    }
};

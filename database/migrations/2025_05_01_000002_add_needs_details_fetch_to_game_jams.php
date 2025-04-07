<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_jams', function (Blueprint $table) {
            $table->boolean('needs_details_fetch')->default(true)->after('logo_url');
        });
    }

    public function down(): void
    {
        Schema::table('game_jams', function (Blueprint $table) {
            $table->dropColumn('needs_details_fetch');
        });
    }
};

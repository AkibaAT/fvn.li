<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('suggested_price');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->float('suggested_price')->nullable()->after('min_price');
        });
    }
};

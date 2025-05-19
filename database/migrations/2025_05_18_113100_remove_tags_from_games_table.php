<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('games', 'tags')) {
            Schema::table('games', function (Blueprint $table) {
                $table->dropColumn('tags');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('games', 'tags')) {
            Schema::table('games', function (Blueprint $table) {
                $table->string('tags')->nullable()->after('blur_screenshots');
            });
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raters', function (Blueprint $table) {
            $table->boolean('is_review_banned')->default(false)->after('external_platform');
        });
    }

    public function down(): void
    {
        Schema::table('raters', function (Blueprint $table) {
            $table->dropColumn('is_review_banned');
        });
    }
};

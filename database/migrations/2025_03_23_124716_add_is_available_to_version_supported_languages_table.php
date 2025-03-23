<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_supported_languages', function (Blueprint $table) {
            $table->boolean('is_available')->default(true)->after('iso_code');
        });
    }

    public function down(): void
    {
        Schema::table('version_supported_languages', function (Blueprint $table) {
            $table->dropColumn('is_available');
        });
    }
};

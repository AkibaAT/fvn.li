<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_route_labels', function (Blueprint $table) {
            $table->boolean('returns_to_caller')->default(false)->after('is_ending');
        });
    }

    public function down(): void
    {
        Schema::table('version_route_labels', function (Blueprint $table) {
            $table->dropColumn('returns_to_caller');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('version_route_edges', 'condition')) {
            Schema::table('version_route_edges', function (Blueprint $table) {
                $table->text('condition')->nullable()->after('edge_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('version_route_edges', 'condition')) {
            Schema::table('version_route_edges', function (Blueprint $table) {
                $table->dropColumn('condition');
            });
        }
    }
};

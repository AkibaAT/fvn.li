<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_route_labels', function (Blueprint $table): void {
            $table->boolean('is_scaffolding')->default(false);
            $table->index(['game_version_id', 'is_scaffolding'], 'vrl_version_is_scaffolding_index');
        });
    }

    public function down(): void
    {
        Schema::table('version_route_labels', function (Blueprint $table): void {
            $table->dropIndex('vrl_version_is_scaffolding_index');
            $table->dropColumn('is_scaffolding');
        });
    }
};

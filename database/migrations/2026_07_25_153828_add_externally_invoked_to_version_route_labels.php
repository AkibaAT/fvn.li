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
            // Set when Python outside the script flow hands control to this
            // label by name, which makes it an entry point of the route graph
            // rather than an unreachable node.
            $table->boolean('externally_invoked')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('version_route_labels', function (Blueprint $table): void {
            $table->dropColumn('externally_invoked');
        });
    }
};

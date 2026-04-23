<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_route_variable_changes', function (Blueprint $table) {
            $table->text('condition')->nullable()->after('context');
            $table->json('condition_stack')->nullable()->after('condition');
        });
    }

    public function down(): void
    {
        Schema::table('version_route_variable_changes', function (Blueprint $table) {
            $table->dropColumn(['condition', 'condition_stack']);
        });
    }
};

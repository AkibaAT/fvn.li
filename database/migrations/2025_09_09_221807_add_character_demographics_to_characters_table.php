<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('display_name_corrections');
            $table->string('species')->nullable()->after('gender');
            $table->string('age')->nullable()->after('species');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['gender', 'species', 'age']);
        });
    }
};

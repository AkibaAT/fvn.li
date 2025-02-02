<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('version_file_types', function (Blueprint $table) {
            $table->string('extension', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('version_file_types', function (Blueprint $table) {
            $table->string('extension', 10)->change();
        });
    }
};

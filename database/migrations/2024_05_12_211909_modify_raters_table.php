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
            $table->caseInsensitiveText('name')->change();
            $table->caseInsensitiveText('username')->nullable();
            $table->index(['name']);
        });
    }

    public function down(): void
    {
        Schema::table('raters', function (Blueprint $table) {
            $table->string('name', 100)->change();
            $table->dropColumn('username');
            $table->dropIndex(['name']);
        });
    }
};

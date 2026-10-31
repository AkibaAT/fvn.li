<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->timestamp('delisted_at')->nullable();
            $table->enum('delisted_reason', ['page_removed', 'password_protected', 'no_downloads', 'blocked'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['delisted_at', 'delisted_reason']);
        });
    }
};

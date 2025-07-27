<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('click_stats', function (Blueprint $table) {
            // Change referrer from varchar(255) to text to handle long URLs
            $table->text('referrer')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('click_stats', function (Blueprint $table) {
            // Revert back to varchar(255)
            $table->string('referrer')->nullable()->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            // Change token column from varchar(255) to text to accommodate longer OAuth tokens
            // Google OAuth tokens can exceed 255 characters
            $table->text('token')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            // Revert to varchar(255)
            $table->string('token', 255)->nullable()->change();
        });
    }
};

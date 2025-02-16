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
            $table->boolean('is_suspicious')->default(false);
            $table->string('suspicion_reason')->nullable();
            $table->timestamp('marked_suspicious_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('raters', function (Blueprint $table) {
            $table->dropColumn(['is_suspicious', 'suspicion_reason', 'marked_suspicious_at']);
        });
    }
};

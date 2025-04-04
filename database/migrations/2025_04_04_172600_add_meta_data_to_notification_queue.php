<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_queue', function (Blueprint $table) {
            $table->jsonb('meta_data')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('notification_queue', function (Blueprint $table) {
            $table->dropColumn('meta_data');
        });
    }
};

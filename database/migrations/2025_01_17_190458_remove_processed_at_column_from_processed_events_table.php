<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processed_events', function (Blueprint $table) {
            DB::statement('UPDATE processed_events SET updated_at = processed_at, created_at = processed_at WHERE created_at IS NULL');
            $table->dropColumn('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('processed_events', function (Blueprint $table) {
            $table->timestamp('processed_at');
            DB::statement('UPDATE processed_events SET processed_at = created_at');
        });
    }
};

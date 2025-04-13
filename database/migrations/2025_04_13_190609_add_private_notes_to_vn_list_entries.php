<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vn_list_entries', function (Blueprint $table) {
            $table->text('private_notes')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('vn_list_entries', function (Blueprint $table) {
            $table->dropColumn('private_notes');
        });
    }
};

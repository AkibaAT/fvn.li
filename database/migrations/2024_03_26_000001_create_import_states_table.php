<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_states', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->bigInteger('last_processed_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_states');
    }
};

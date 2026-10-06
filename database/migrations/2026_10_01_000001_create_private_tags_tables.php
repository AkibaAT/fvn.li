<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 64);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX private_tags_user_name_unique ON private_tags (user_id, lower(name))');

        Schema::create('private_tag_games', function (Blueprint $table) {
            $table->foreignId('private_tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->primary(['private_tag_id', 'game_id']);
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_tag_games');
        Schema::dropIfExists('private_tags');
    }
};

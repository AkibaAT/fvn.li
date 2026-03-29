<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('version_route_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('file_path');
            $table->integer('line_number');
            $table->timestamps();

            $table->unique(['game_version_id', 'name']);
            $table->index('name');
        });

        Schema::create('version_route_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->onDelete('cascade');
            $table->string('from_label');
            $table->string('to_label');
            $table->string('edge_type', 20)->default('flow');
            $table->text('condition')->nullable();
            $table->string('file_path')->nullable();
            $table->integer('line_number')->default(0);
            $table->timestamps();

            $table->index(['game_version_id', 'from_label']);
            $table->index(['game_version_id', 'to_label']);
            $table->index('edge_type');
        });

        Schema::create('version_route_menu_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->onDelete('cascade');
            $table->string('from_label');
            $table->text('text')->nullable();
            $table->text('condition')->nullable();
            $table->string('target_label')->nullable();
            $table->string('edge_type', 20)->nullable();
            $table->string('file_path')->nullable();
            $table->integer('line_number')->default(0);
            $table->timestamps();

            $table->index(['game_version_id', 'from_label']);
            $table->index(['game_version_id', 'target_label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_route_menu_choices');
        Schema::dropIfExists('version_route_edges');
        Schema::dropIfExists('version_route_labels');
    }
};

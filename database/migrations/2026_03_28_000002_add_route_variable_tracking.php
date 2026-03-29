<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('version_route_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('default_value')->nullable();
            $table->string('type', 20)->default('default');
            $table->string('file_path')->nullable();
            $table->integer('line_number')->default(0);
            $table->timestamps();

            $table->unique(['game_version_id', 'name']);
            $table->index(['game_version_id', 'type']);
        });

        Schema::create('version_route_variable_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_version_id')->constrained()->onDelete('cascade');
            $table->string('label');
            $table->string('variable_name');
            $table->string('operation', 10)->default('=');
            $table->text('value')->nullable();
            $table->string('file_path')->nullable();
            $table->integer('line_number')->default(0);
            $table->string('context', 30)->nullable();
            $table->timestamps();

            $table->index(['game_version_id', 'label']);
            $table->index(['game_version_id', 'variable_name']);
        });

        Schema::table('version_route_labels', function (Blueprint $table) {
            $table->boolean('is_ending')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_route_variable_changes');
        Schema::dropIfExists('version_route_variables');
        Schema::table('version_route_labels', function (Blueprint $table) {
            $table->dropColumn('is_ending');
        });
    }
};

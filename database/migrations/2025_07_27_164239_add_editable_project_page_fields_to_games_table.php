<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Add fields for editable project pages
            $table->boolean('has_custom_page')->default(false)->after('custom_css');
            $table->text('custom_description')->nullable()->after('has_custom_page');
            $table->json('custom_screenshots')->nullable()->after('custom_description');
            $table->json('custom_assets')->nullable()->after('custom_screenshots');
            $table->timestamp('custom_page_updated_at')->nullable()->after('custom_assets');
            $table->unsignedBigInteger('custom_page_updated_by')->nullable()->after('custom_page_updated_at');

            // Add foreign key for the user who last updated the custom page
            $table->foreign('custom_page_updated_by')->references('id')->on('users')->onDelete('set null');

            // Add index for performance
            $table->index(['has_custom_page', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['custom_page_updated_by']);
            $table->dropIndex(['has_custom_page', 'is_visible']);
            $table->dropColumn([
                'has_custom_page',
                'custom_description',
                'custom_screenshots',
                'custom_assets',
                'custom_page_updated_at',
                'custom_page_updated_by',
            ]);
        });
    }
};

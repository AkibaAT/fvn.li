<?php

declare(strict_types=1);

use App\Services\HtmlSanitizerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sanitizer = app(HtmlSanitizerService::class);

        DB::table('games')
            ->select(['id', 'authors'])
            ->whereNotNull('authors')
            ->where('authors', '!=', '')
            ->chunkById(500, function ($games) use ($sanitizer): void {
                foreach ($games as $game) {
                    $sanitized = $sanitizer->sanitizeAuthors($game->authors);

                    if ($sanitized !== $game->authors) {
                        DB::table('games')->where('id', $game->id)->update(['authors' => $sanitized]);
                    }
                }
            });
    }

    public function down(): void {}
};

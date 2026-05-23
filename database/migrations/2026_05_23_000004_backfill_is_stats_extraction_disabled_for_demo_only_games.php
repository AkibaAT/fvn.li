<?php

declare(strict_types=1);

use App\ValueObjects\Upload;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('games')
            ->where('game_engine', "Ren'Py")
            ->where('is_paid', true)
            ->where('is_stats_extraction_disabled', false)
            ->whereNotNull('uploads')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $game): void {
                $uploads = $this->decodeUploads($game->uploads);
                if ($uploads === []) {
                    return;
                }

                $processableUploads = $this->processableUploads($uploads);
                if ($processableUploads->isEmpty() || $processableUploads->contains(fn (Upload $upload) => ! $upload->isDemo())) {
                    return;
                }

                DB::table('games')
                    ->where('id', $game->id)
                    ->update(['is_stats_extraction_disabled' => true]);

                $this->clearVersionStats((int) $game->id);
            });
    }

    public function down(): void
    {
        // Deliberately do not restore deleted derived stats.
    }

    private function decodeUploads(mixed $uploads): array
    {
        if (is_string($uploads)) {
            $uploads = json_decode($uploads, true);
        }

        return is_array($uploads) ? $uploads : [];
    }

    private function processableUploads(array $uploads): Collection
    {
        return collect($uploads)
            ->map(function (mixed $upload, int|string $id): ?Upload {
                if (! is_array($upload)) {
                    return null;
                }

                if (empty($upload['updated_at'])) {
                    return null;
                }

                try {
                    $upload = Upload::fromArray($upload, (int) $id);
                } catch (Throwable) {
                    return null;
                }

                return $upload->isProcessable() ? $upload : null;
            })
            ->filter()
            ->values();
    }

    private function clearVersionStats(int $gameId): void
    {
        $versionIds = DB::table('game_versions')
            ->where('game_id', $gameId)
            ->pluck('id');

        if ($versionIds->isEmpty()) {
            return;
        }

        DB::table('version_word_frequencies')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_dialogue_lines')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_character_stats')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_language_stats')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_file_categories')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_route_paths')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_route_variable_changes')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_route_variables')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_route_menu_choices')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_route_edges')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('version_route_labels')->whereIn('game_version_id', $versionIds)->delete();
        DB::table('game_versions')->whereIn('id', $versionIds)->update(['route_graph_data' => null]);
    }
};

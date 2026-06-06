<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\NotificationHistory;
use App\Models\Rating;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\VnList;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class UserDataExportController extends Controller
{
    public function exportUserData(): StreamedResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            abort(401, 'Unauthenticated');
        }
        $user = User::findOrFail($authId);

        // Enhanced profile data with complete social account information
        $profile = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at?->toISOString(),
            'providers' => $user->socialAccounts()->pluck('provider_name')->values(),
        ];

        // Complete social account data
        $socialAccounts = $user->socialAccounts()->get()->map(function ($account) {
            return [
                'id' => $account->id,
                'provider_name' => $account->provider_name,
                'provider_id' => $account->provider_id,
                'provider_data' => $account->provider_data,
                'created_at' => $account->created_at?->toISOString(),
                'updated_at' => $account->updated_at?->toISOString(),
            ];
        })->values();

        $lists = (VnList::where('user_id', $user->id)
            ->with([
                'entries' => function ($q) {
                    $q->with([
                        'game' => function ($gq) {
                            $gq->select('id', 'name', 'slug', 'is_visible');
                        },
                    ]);
                    $q->orderBy('sort_order');
                },
            ])
            ->orderBy('created_at', 'desc')
            ->get())->map(function ($l) {
                return [
                    'id' => $l->id,
                    'name' => $l->name,
                    'description' => $l->description,
                    'type' => $l->type,
                    'is_public' => (bool) $l->is_public,
                    'is_default' => (bool) $l->is_default,
                    'created_at' => $l->created_at?->toISOString(),
                    'updated_at' => $l->updated_at?->toISOString(),
                    'entries' => $l->entries->map(function ($e) {
                        return [
                            'id' => $e->id,
                            'game_id' => $e->game_id,
                            'sort_order' => (int) $e->sort_order,
                            'private_notes' => $e->private_notes,
                            'created_at' => $e->created_at?->toISOString(),
                            'updated_at' => $e->updated_at?->toISOString(),
                            'game' => $e->game ? [
                                'id' => $e->game->id,
                                'name' => $e->game->name,
                                'slug' => $e->game->slug,
                                'is_visible' => (bool) $e->game->is_visible,
                            ] : null,
                        ];
                    })->values(),
                ];
            })->values();

        $ratings = (Rating::where('user_id', $user->id)
            ->orderBy('published_at', 'desc')
            ->get([
                'id', 'game_id', 'rating', 'is_reviewed', 'published_at', 'created_at', 'updated_at', 'review',
            ]))->map(function ($r) {
                return [
                    'id' => $r->id,
                    'game_id' => $r->game_id,
                    'rating' => $r->rating,
                    'is_reviewed' => (bool) $r->is_reviewed,
                    'content' => $r->review,
                    'published_at' => $r->published_at?->toISOString(),
                    'created_at' => $r->created_at?->toISOString(),
                    'updated_at' => $r->updated_at?->toISOString(),
                ];
            })->values();

        // Game progress data
        $gameProgress = UserGameProgress::where('user_id', $user->id)
            ->with([
                'game' => function ($q) {
                    $q->select('id', 'name', 'slug');
                }, 'gameVersion' => function ($q) {
                    $q->select('id', 'version', 'published_at');
                },
            ])
            ->get()
            ->map(function ($progress) {
                return [
                    'id' => $progress->id,
                    'game_id' => $progress->game_id,
                    'game_version_id' => $progress->game_version_id,
                    'status' => $progress->status,
                    'progress' => $progress->progress,
                    'personal_notes' => $progress->personal_notes,
                    'started_at' => $progress->started_at?->toISOString(),
                    'completed_at' => $progress->completed_at?->toISOString(),
                    'receive_updates' => (bool) $progress->receive_updates,
                    'created_at' => $progress->created_at?->toISOString(),
                    'updated_at' => $progress->updated_at?->toISOString(),
                    'game' => $progress->game ? [
                        'id' => $progress->game->id,
                        'name' => $progress->game->name,
                        'slug' => $progress->game->slug,
                    ] : null,
                    'game_version' => $progress->gameVersion ? [
                        'id' => $progress->gameVersion->id,
                        'version' => $progress->gameVersion->version,
                        'published_at' => $progress->gameVersion->published_at?->toISOString(),
                    ] : null,
                ];
            })->values();

        // Notification preferences
        $notificationPreferences = $user->notificationPreferences()
            ->get()
            ->map(function ($preference) {
                return [
                    'id' => $preference->id,
                    'browser_notifications_enabled' => (bool) $preference->browser_notifications_enabled,
                    'discord_notifications_enabled' => (bool) $preference->discord_notifications_enabled,
                    'notification_digest' => $preference->notification_digest,
                    'created_at' => $preference->created_at?->toISOString(),
                    'updated_at' => $preference->updated_at?->toISOString(),
                ];
            })->values();

        // Notification history
        $notificationHistory = NotificationHistory::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'message' => $notification->message,
                    'data' => $notification->data,
                    'created_at' => $notification->created_at?->toISOString(),
                ];
            })->values();

        // Ignored games
        $ignoredGames = $user->ignoredGames()
            ->orderBy('user_ignored_games.created_at', 'desc')
            ->get()
            ->map(function ($game) {
                return [
                    'id' => $game->id,
                    'name' => $game->name,
                    'slug' => $game->slug,
                    'platform' => $game->platform,
                    'ignored_at' => $game->pivot->created_at?->toISOString(),
                ];
            })->values();

        $filename = 'user-data-' . ($user->name ? preg_replace('/[^a-z0-9\-]+/i', '-',
            strtolower($user->name)) : 'export') . '-' . now()->format('Ymd-His') . '.zip';

        return new StreamedResponse(function () use (
            $profile,
            $socialAccounts,
            $lists,
            $gameProgress,
            $notificationPreferences,
            $notificationHistory,
            $ignoredGames
        ) {
            $tmp = fopen('php://temp', 'w+');
            $zip = new ZipArchive;
            $status = $zip->open(stream_get_meta_data($tmp)['uri'], ZipArchive::OVERWRITE);
            if ($status !== true) {
                $path = tempnam(sys_get_temp_dir(), 'expzip');
                $zip = new ZipArchive;
                if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException('Unable to create ZIP archive');
                }

                $this->addExportFilesToZip(
                    $zip,
                    $profile,
                    $socialAccounts,
                    $lists,
                    $gameProgress,
                    $notificationPreferences,
                    $notificationHistory,
                    $ignoredGames
                );
                $zip->close();

                $out = fopen($path, 'rb');
                stream_copy_to_stream($out, fopen('php://output', 'wb'));
                fclose($out);
                @unlink($path);

                return;
            }

            $this->addExportFilesToZip(
                $zip,
                $profile,
                $socialAccounts,
                $lists,
                $gameProgress,
                $notificationPreferences,
                $notificationHistory,
                $ignoredGames
            );
            $zip->close();
            rewind($tmp);
            stream_copy_to_stream($tmp, fopen('php://output', 'wb'));
            fclose($tmp);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    private function addExportFilesToZip(
        ZipArchive $zip,
        array $profile,
        Collection $socialAccounts,
        Collection $lists,
        Collection $gameProgress,
        Collection $notificationPreferences,
        Collection $notificationHistory,
        Collection $ignoredGames
    ): void {
        $zip->addFromString('profile.json', json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('social_accounts.json',
            json_encode($socialAccounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('lists.json', json_encode($lists, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('game_progress.json',
            json_encode($gameProgress, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('notification_preferences.json',
            json_encode($notificationPreferences, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('notification_history.json',
            json_encode($notificationHistory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $profileCsv = fopen('php://temp', 'w+');
        fputcsv($profileCsv, ['id', 'name', 'email', 'created_at', 'providers'], ',', '"', '\\');
        fputcsv($profileCsv, [
            $profile['id'],
            $profile['name'],
            $profile['email'],
            $profile['created_at'],
            implode('|', $profile['providers']->toArray()),
        ], ',', '"', '\\');
        rewind($profileCsv);
        $zip->addFromString('profile.csv', stream_get_contents($profileCsv));
        fclose($profileCsv);

        $listsCsv = fopen('php://temp', 'w+');
        fputcsv($listsCsv,
            ['id', 'name', 'description', 'type', 'is_public', 'is_default', 'created_at', 'updated_at', 'entry_count'],
            ',', '"', '\\');
        foreach ($lists as $l) {
            fputcsv($listsCsv, [
                $l['id'],
                $l['name'],
                $l['description'],
                $l['type'],
                $l['is_public'] ? 1 : 0,
                $l['is_default'] ? 1 : 0,
                $l['created_at'],
                $l['updated_at'],
                is_countable($l['entries']) ? count($l['entries']) : 0,
            ], ',', '"', '\\');
        }
        rewind($listsCsv);
        $zip->addFromString('lists.csv', stream_get_contents($listsCsv));
        fclose($listsCsv);

        $entriesCsv = fopen('php://temp', 'w+');
        fputcsv($entriesCsv, [
            'list_id', 'entry_id', 'game_id', 'game_name', 'game_slug', 'sort_order', 'private_notes', 'created_at',
            'updated_at',
        ], ',', '"', '\\');
        foreach ($lists as $l) {
            foreach ($l['entries'] as $e) {
                fputcsv($entriesCsv, [
                    $l['id'],
                    $e['id'],
                    $e['game_id'],
                    $e['game']['name'] ?? null,
                    $e['game']['slug'] ?? null,
                    $e['sort_order'],
                    $e['private_notes'],
                    $e['created_at'],
                    $e['updated_at'],
                ], ',', '"', '\\');
            }
        }
        rewind($entriesCsv);
        $zip->addFromString('list_entries.csv', stream_get_contents($entriesCsv));
        fclose($entriesCsv);

        // Ratings CSV removed

        // Social accounts CSV
        $socialAccountsCsv = fopen('php://temp', 'w+');
        fputcsv($socialAccountsCsv, ['id', 'provider_name', 'provider_id', 'created_at', 'updated_at'], ',', '"', '\\');
        foreach ($socialAccounts as $sa) {
            fputcsv($socialAccountsCsv, [
                $sa['id'],
                $sa['provider_name'],
                $sa['provider_id'],
                $sa['created_at'],
                $sa['updated_at'],
            ], ',', '"', '\\');
        }
        rewind($socialAccountsCsv);
        $zip->addFromString('social_accounts.csv', stream_get_contents($socialAccountsCsv));
        fclose($socialAccountsCsv);

        // Game progress CSV
        $gameProgressCsv = fopen('php://temp', 'w+');
        fputcsv($gameProgressCsv, [
            'id', 'game_id', 'game_name', 'game_version_id', 'version', 'status', 'progress', 'personal_notes',
            'started_at', 'completed_at', 'receive_updates', 'created_at', 'updated_at',
        ], ',', '"', '\\');
        foreach ($gameProgress as $gp) {
            fputcsv($gameProgressCsv, [
                $gp['id'],
                $gp['game_id'],
                $gp['game']['name'] ?? null,
                $gp['game_version_id'],
                $gp['game_version']['version'] ?? null,
                $gp['status'],
                $gp['progress'],
                $gp['personal_notes'],
                $gp['started_at'],
                $gp['completed_at'],
                $gp['receive_updates'] ? 1 : 0,
                $gp['created_at'],
                $gp['updated_at'],
            ], ',', '"', '\\');
        }
        rewind($gameProgressCsv);
        $zip->addFromString('game_progress.csv', stream_get_contents($gameProgressCsv));
        fclose($gameProgressCsv);

        // Notification preferences CSV
        $notificationPreferencesCsv = fopen('php://temp', 'w+');
        fputcsv($notificationPreferencesCsv, [
            'id', 'browser_notifications_enabled', 'discord_notifications_enabled', 'notification_digest', 'created_at',
            'updated_at',
        ], ',', '"', '\\');
        foreach ($notificationPreferences as $np) {
            fputcsv($notificationPreferencesCsv, [
                $np['id'],
                $np['browser_notifications_enabled'] ? 1 : 0,
                $np['discord_notifications_enabled'] ? 1 : 0,
                $np['notification_digest'],
                $np['created_at'],
                $np['updated_at'],
            ], ',', '"', '\\');
        }
        rewind($notificationPreferencesCsv);
        $zip->addFromString('notification_preferences.csv', stream_get_contents($notificationPreferencesCsv));
        fclose($notificationPreferencesCsv);

        // Notification history CSV
        $notificationHistoryCsv = fopen('php://temp', 'w+');
        fputcsv($notificationHistoryCsv, ['id', 'type', 'message', 'created_at'], ',', '"', '\\');
        foreach ($notificationHistory as $nh) {
            fputcsv($notificationHistoryCsv, [
                $nh['id'],
                $nh['type'],
                $nh['message'],
                $nh['created_at'],
            ], ',', '"', '\\');
        }
        rewind($notificationHistoryCsv);
        $zip->addFromString('notification_history.csv', stream_get_contents($notificationHistoryCsv));
        fclose($notificationHistoryCsv);

        // Ignored games JSON and CSV
        $zip->addFromString('ignored_games.json',
            json_encode($ignoredGames, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $ignoredGamesCsv = fopen('php://temp', 'w+');
        fputcsv($ignoredGamesCsv, ['id', 'name', 'slug', 'platform', 'ignored_at'], ',', '"', '\\');
        foreach ($ignoredGames as $ig) {
            fputcsv($ignoredGamesCsv, [
                $ig['id'],
                $ig['name'],
                $ig['slug'],
                $ig['platform'],
                $ig['ignored_at'],
            ], ',', '"', '\\');
        }
        rewind($ignoredGamesCsv);
        $zip->addFromString('ignored_games.csv', stream_get_contents($ignoredGamesCsv));
        fclose($ignoredGamesCsv);
    }
}

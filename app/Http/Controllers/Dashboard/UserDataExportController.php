<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BugReport;
use App\Models\BugReportComment;
use App\Models\Game;
use App\Models\NotificationHistory;
use App\Models\Rating;
use App\Models\ReviewReport;
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

        $ratings = Rating::query()
            ->authoredBy($user->id)
            ->with(['game:id,name,slug'])
            ->orderBy('published_at', 'desc')
            ->get([
                'id', 'game_id', 'rating', 'is_reviewed', 'source_platform',
                'published_at', 'created_at', 'updated_at', 'review', 'external_metadata',
            ])
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'game_id' => $r->game_id,
                    'game' => $r->game ? [
                        'id' => $r->game->id,
                        'name' => $r->game->name,
                        'slug' => $r->game->slug,
                    ] : null,
                    'rating' => $r->rating,
                    'is_reviewed' => (bool) $r->is_reviewed,
                    'source_platform' => $r->source_platform,
                    'content' => $r->review,
                    'merged_reviews' => $r->external_metadata['merged_reviews'] ?? [],
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
                    'game_id' => $notification->game_id,
                    'game_version_id' => $notification->game_version_id,
                    'success' => $notification->success,
                    'meta_data' => $notification->meta_data,
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

        $additionalData = [
            'search_preferences' => $user->preferences()->get(['preferred_languages', 'excluded_tags'])->toArray(),
            'bug_reports' => BugReport::where('user_id', $user->id)->get([
                'id', 'page_url', 'page_title', 'description', 'request_parameters', 'user_agent',
                'status', 'is_closed', 'resolved_at', 'created_at', 'updated_at',
            ])->toArray(),
            'bug_report_comments' => BugReportComment::where('user_id', $user->id)
                ->orWhereHas('bugReport', fn ($query) => $query->where('user_id', $user->id))
                ->get(['id', 'bug_report_id', 'user_id', 'message', 'is_from_admin', 'created_at', 'updated_at'])->toArray(),
            'addition_requests' => $user->additionRequests()->get()->map(fn ($request) => $request->only([
                'id', 'game_url', 'status', 'rejection_reason', 'game_id', 'created_at', 'updated_at',
            ]))->all(),
            'review_reports' => ReviewReport::where('reporter_id', $user->id)->get([
                'id', 'rating_id', 'reason', 'details', 'status', 'created_at', 'updated_at',
            ])->toArray(),
        ];

        $filename = 'user-data-' . ($user->name ? preg_replace('/[^a-z0-9\-]+/i', '-',
            strtolower($user->name)) : 'export') . '-' . now()->format('Ymd-His') . '.zip';

        return new StreamedResponse(function () use (
            $profile,
            $socialAccounts,
            $lists,
            $ratings,
            $gameProgress,
            $notificationPreferences,
            $notificationHistory,
            $ignoredGames,
            $additionalData
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
                    $ratings,
                    $gameProgress,
                    $notificationPreferences,
                    $notificationHistory,
                    $ignoredGames,
                    $additionalData
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
                $ratings,
                $gameProgress,
                $notificationPreferences,
                $notificationHistory,
                $ignoredGames,
                $additionalData
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
        Collection $ratings,
        Collection $gameProgress,
        Collection $notificationPreferences,
        Collection $notificationHistory,
        Collection $ignoredGames,
        array $additionalData
    ): void {
        $zip->addFromString('profile.json', json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('social_accounts.json',
            json_encode($socialAccounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('lists.json', json_encode($lists, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('ratings.json', json_encode($ratings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('game_progress.json',
            json_encode($gameProgress, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('notification_preferences.json',
            json_encode($notificationPreferences, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('notification_history.json',
            json_encode($notificationHistory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $profileCsv = fopen('php://temp', 'w+');
        $this->writeCsv($profileCsv, ['id', 'name', 'email', 'created_at', 'providers']);
        $this->writeCsv($profileCsv, [
            $profile['id'],
            $profile['name'],
            $profile['email'],
            $profile['created_at'],
            implode('|', $profile['providers']->toArray()),
        ]);
        rewind($profileCsv);
        $zip->addFromString('profile.csv', stream_get_contents($profileCsv));
        fclose($profileCsv);

        $listsCsv = fopen('php://temp', 'w+');
        $this->writeCsv($listsCsv,
            ['id', 'name', 'description', 'type', 'is_public', 'is_default', 'created_at', 'updated_at', 'entry_count'],
        );
        foreach ($lists as $l) {
            $this->writeCsv($listsCsv, [
                $l['id'],
                $l['name'],
                $l['description'],
                $l['type'],
                $l['is_public'] ? 1 : 0,
                $l['is_default'] ? 1 : 0,
                $l['created_at'],
                $l['updated_at'],
                is_countable($l['entries']) ? count($l['entries']) : 0,
            ]);
        }
        rewind($listsCsv);
        $zip->addFromString('lists.csv', stream_get_contents($listsCsv));
        fclose($listsCsv);

        $entriesCsv = fopen('php://temp', 'w+');
        $this->writeCsv($entriesCsv, [
            'list_id', 'entry_id', 'game_id', 'game_name', 'game_slug', 'sort_order', 'private_notes', 'created_at',
            'updated_at',
        ]);
        foreach ($lists as $l) {
            foreach ($l['entries'] as $e) {
                $this->writeCsv($entriesCsv, [
                    $l['id'],
                    $e['id'],
                    $e['game_id'],
                    $e['game']['name'] ?? null,
                    $e['game']['slug'] ?? null,
                    $e['sort_order'],
                    $e['private_notes'],
                    $e['created_at'],
                    $e['updated_at'],
                ]);
            }
        }
        rewind($entriesCsv);
        $zip->addFromString('list_entries.csv', stream_get_contents($entriesCsv));
        fclose($entriesCsv);

        $ratingsCsv = fopen('php://temp', 'w+');
        $this->writeCsv($ratingsCsv, [
            'id', 'game_id', 'game_name', 'game_slug', 'rating', 'is_reviewed', 'source_platform', 'content',
            'published_at', 'created_at', 'updated_at', 'merged_reviews',
        ]);
        foreach ($ratings as $r) {
            $this->writeCsv($ratingsCsv, [
                $r['id'],
                $r['game_id'],
                $r['game']['name'] ?? null,
                $r['game']['slug'] ?? null,
                $r['rating'],
                $r['is_reviewed'] ? 1 : 0,
                $r['source_platform'],
                $r['content'],
                $r['published_at'],
                $r['created_at'],
                $r['updated_at'],
                json_encode($r['merged_reviews'], JSON_UNESCAPED_SLASHES),
            ]);
        }
        rewind($ratingsCsv);
        $zip->addFromString('ratings.csv', stream_get_contents($ratingsCsv));
        fclose($ratingsCsv);

        // Social accounts CSV
        $socialAccountsCsv = fopen('php://temp', 'w+');
        $this->writeCsv($socialAccountsCsv, ['id', 'provider_name', 'provider_id', 'created_at', 'updated_at']);
        foreach ($socialAccounts as $sa) {
            $this->writeCsv($socialAccountsCsv, [
                $sa['id'],
                $sa['provider_name'],
                $sa['provider_id'],
                $sa['created_at'],
                $sa['updated_at'],
            ]);
        }
        rewind($socialAccountsCsv);
        $zip->addFromString('social_accounts.csv', stream_get_contents($socialAccountsCsv));
        fclose($socialAccountsCsv);

        // Game progress CSV
        $gameProgressCsv = fopen('php://temp', 'w+');
        $this->writeCsv($gameProgressCsv, [
            'id', 'game_id', 'game_name', 'game_version_id', 'version', 'status', 'personal_notes',
            'started_at', 'completed_at', 'receive_updates', 'created_at', 'updated_at',
        ]);
        foreach ($gameProgress as $gp) {
            $this->writeCsv($gameProgressCsv, [
                $gp['id'],
                $gp['game_id'],
                $gp['game']['name'] ?? null,
                $gp['game_version_id'],
                $gp['game_version']['version'] ?? null,
                $gp['status'],
                $gp['personal_notes'],
                $gp['started_at'],
                $gp['completed_at'],
                $gp['receive_updates'] ? 1 : 0,
                $gp['created_at'],
                $gp['updated_at'],
            ]);
        }
        rewind($gameProgressCsv);
        $zip->addFromString('game_progress.csv', stream_get_contents($gameProgressCsv));
        fclose($gameProgressCsv);

        // Notification preferences CSV
        $notificationPreferencesCsv = fopen('php://temp', 'w+');
        $this->writeCsv($notificationPreferencesCsv, [
            'id', 'browser_notifications_enabled', 'discord_notifications_enabled', 'notification_digest', 'created_at',
            'updated_at',
        ]);
        foreach ($notificationPreferences as $np) {
            $this->writeCsv($notificationPreferencesCsv, [
                $np['id'],
                $np['browser_notifications_enabled'] ? 1 : 0,
                $np['discord_notifications_enabled'] ? 1 : 0,
                $np['notification_digest'],
                $np['created_at'],
                $np['updated_at'],
            ]);
        }
        rewind($notificationPreferencesCsv);
        $zip->addFromString('notification_preferences.csv', stream_get_contents($notificationPreferencesCsv));
        fclose($notificationPreferencesCsv);

        // Notification history CSV
        $notificationHistoryCsv = fopen('php://temp', 'w+');
        $this->writeCsv($notificationHistoryCsv, ['id', 'type', 'game_id', 'game_version_id', 'success', 'meta_data', 'created_at']);
        foreach ($notificationHistory as $nh) {
            $this->writeCsv($notificationHistoryCsv, [
                $nh['id'],
                $nh['type'],
                $nh['game_id'],
                $nh['game_version_id'],
                $nh['success'] ? 1 : 0,
                json_encode($nh['meta_data'], JSON_UNESCAPED_SLASHES),
                $nh['created_at'],
            ]);
        }
        rewind($notificationHistoryCsv);
        $zip->addFromString('notification_history.csv', stream_get_contents($notificationHistoryCsv));
        fclose($notificationHistoryCsv);

        // Ignored games JSON and CSV
        $zip->addFromString('ignored_games.json',
            json_encode($ignoredGames, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $ignoredGamesCsv = fopen('php://temp', 'w+');
        $this->writeCsv($ignoredGamesCsv, ['id', 'name', 'slug', 'platform', 'ignored_at']);
        foreach ($ignoredGames as $ig) {
            $this->writeCsv($ignoredGamesCsv, [
                $ig['id'],
                $ig['name'],
                $ig['slug'],
                $ig['platform'],
                $ig['ignored_at'],
            ]);
        }
        rewind($ignoredGamesCsv);
        $zip->addFromString('ignored_games.csv', stream_get_contents($ignoredGamesCsv));
        fclose($ignoredGamesCsv);

        foreach ($additionalData as $name => $rows) {
            $zip->addFromString("{$name}.json", json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $csv = fopen('php://temp', 'w+');
            if ($rows !== []) {
                $this->writeCsv($csv, array_keys($rows[0]));
                foreach ($rows as $row) {
                    $this->writeCsv($csv, array_map(fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value, $row));
                }
            }
            rewind($csv);
            $zip->addFromString("{$name}.csv", stream_get_contents($csv));
            fclose($csv);
        }
    }

    private function writeCsv($stream, array $row): void
    {
        $row = array_map(fn ($value) => is_string($value) && preg_match('/^(?:[\t\r\n]|\s*[=+@-])/u', $value)
            ? "'" . $value : $value, $row);
        fputcsv($stream, $row, ',', '"', '');
    }
}

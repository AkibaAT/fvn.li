<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ChangeLog;
use App\Models\ClickStat;
use App\Support\SystemAuditUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Throwable;

class UserAccountController extends Controller
{
    public function deleteAccount(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;

        if (! ($request->expectsJson() || $request->ajax())) {
            $request->validate([
                'password' => ['required', 'current_password'],
            ]);
        }
        DB::transaction(function () use ($user, $userId) {
            // Anonymize audit logs (GDPR compliance - retain for legal purposes)
            try {
                $anonymized = ChangeLog::anonymizeUserData($userId);
                Log::info('Anonymized audit logs during account deletion',
                    ['user_id' => $userId, 'anonymized_count' => $anonymized]);
            } catch (Throwable $e) {
                Log::warning('Failed to anonymize audit logs', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }

            // Anonymize click statistics (GDPR compliance - retain for analytics)
            try {
                $anonymized = ClickStat::anonymizePersonalDataForUser($userId);
                Log::info('Anonymized click statistics during account deletion',
                    ['user_id' => $userId, 'anonymized_count' => $anonymized]);
            } catch (Throwable $e) {
                Log::warning('Failed to anonymize click stats', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }

            // Reassign addition request reviews to the anonymous system user.
            $systemAuditUserId = SystemAuditUser::id();
            DB::table('addition_requests')
                ->where('reviewed_by', $userId)
                ->update(['reviewed_by' => $systemAuditUserId]);

            // Reset custom game pages to itch.io synced state
            DB::table('games')
                ->where('custom_page_updated_by', $userId)
                ->update([
                    'custom_page_updated_by' => null,
                    'has_custom_page' => false,
                    'custom_name' => null,
                    'custom_description' => null,
                    'custom_screenshots' => null,
                    'custom_assets' => null,
                    'custom_css' => null,
                    'custom_tags' => [],
                    'custom_page_updated_at' => null,
                ]);

            $user->socialAccounts()->delete();
            if ($user->notificationPreferences) {
                $user->notificationPreferences()->delete();
            }
            $user->pushSubscriptions()->delete();
            $user->vnLists()->each(function ($list) {
                $list->entries()->delete();
                $list->delete();
            });
            $user->gameProgress()->delete();
            $user->notificationHistory()->delete();
            $user->ignoredGames()->detach(); // Remove all ignored games relationships

            DB::table('vn_list_entries')
                ->whereIn('vn_list_id', DB::table('vn_lists')->where('user_id', $userId)->select('id'))
                ->delete();
            DB::table('vn_lists')->where('user_id', $userId)->delete();
            DB::table('user_game_progress')->where('user_id', $userId)->delete();
            DB::table('notification_history')->where('user_id', $userId)->delete();
            DB::table('user_ignored_games')->where('user_id', $userId)->delete();

            // Finally delete the user account
            $user->delete();
            DB::table('users')->where('id', $userId)->delete();
        });

        // Logout the user
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        // For AJAX/JSON requests, return JSON instead of redirect
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Your account has been successfully deleted.',
            ]);
        }

        return Redirect::route('home')
            ->with('success', 'Your account has been successfully deleted.');
    }

    /**
     * Merge social accounts.
     */
    public function mergeSocialAccounts(string $provider)
    {
        $user = Auth::user();

        session(['merging_user_id' => $user->id]);

        // Redirect to the provider's OAuth page
        if ($provider === 'telegram') {
            return redirect()->route('auth.telegram');
        }

        return redirect()->route('auth.redirect', ['provider' => $provider]);
    }

    /**
     * Disconnect a social account.
     */
    public function disconnectSocialAccount(Request $request, string $provider)
    {
        $user = Auth::user();

        // Count total connected providers
        $connectedProvidersCount = $user->socialAccounts()->count();

        // Don't allow disconnecting the last provider
        if ($connectedProvidersCount <= 1) {
            return redirect()->route('dashboard')
                ->with('error',
                    'Cannot disconnect your last social account. Delete your account instead if you wish to completely disconnect.');
        }

        $user->socialAccounts()
            ->where('provider_name', $provider)
            ->delete();

        // For XHR/JSON requests, return JSON instead of redirect
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Successfully disconnected ' . ucfirst($provider) . ' account.',
                'provider' => $provider,
            ]);
        }

        return redirect()->route('dashboard')
            ->with('success', 'Successfully disconnected ' . ucfirst($provider) . ' account.');
    }
}

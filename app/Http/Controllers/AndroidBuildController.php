<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessAndroidBuild;
use App\Models\AndroidBuild;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\AndroidBuildService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AndroidBuildController extends Controller
{
    public function __construct(
        private readonly AndroidBuildService $androidBuildService
    ) {}

    /**
     * Show the user's Android builds
     */
    public function index(): View
    {
        $user = Auth::user();
        $builds = AndroidBuild::where('user_id', $user->id)
            ->with(['game', 'gameVersion'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('user.android-builds', [
            'builds' => $builds,
            'metaTags' => ['title' => 'My Android Builds'],
        ]);
    }

    /**
     * Check if a game is eligible for Android builds
     */
    public function checkEligibility(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|integer|exists:games,id',
            'version_id' => 'nullable|integer|exists:game_versions,id',
        ]);

        $game = Game::findOrFail($request->game_id);
        $version = null;

        if ($request->version_id) {
            $version = GameVersion::findOrFail($request->version_id);
            // Ensure the version belongs to the game
            if ($version->game_id !== $game->id) {
                return response()->json([
                    'eligible' => false,
                    'message' => 'The specified version does not belong to this game.',
                ]);
            }
        }

        $eligible = $this->androidBuildService->isEligibleForAndroidBuild($game, $version);

        return response()->json([
            'eligible' => $eligible,
            'message' => $eligible
                ? 'This game is eligible for Android builds.'
                : 'This game is not eligible for Android builds.',
        ]);
    }

    /**
     * Request an Android build
     */
    public function requestBuild(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|integer|exists:games,id',
            'version_id' => 'nullable|integer|exists:game_versions,id',
        ]);

        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        $game = Game::findOrFail($request->game_id);
        $version = null;

        if ($request->version_id) {
            $version = GameVersion::findOrFail($request->version_id);
            // Ensure the version belongs to the game
            if ($version->game_id !== $game->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'The specified version does not belong to this game.',
                ], 400);
            }
        }

        try {
            // First check for existing builds without creating a new one
            $existingBuild = $this->androidBuildService->requestBuild($user, $game, $version, false);

            // Check if we got an existing completed build
            if ($existingBuild && $existingBuild->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'message' => 'An Android APK is already available for this version.',
                    'build_id' => $existingBuild->id,
                    'status' => 'completed',
                    'download_url' => $this->androidBuildService->getDownloadUrl($existingBuild),
                    'existing' => true,
                ]);
            }

            // Check if we got an existing pending or processing build
            if ($existingBuild && ($existingBuild->status === 'pending' || $existingBuild->status === 'processing')) {
                return response()->json([
                    'success' => true,
                    'message' => $existingBuild->status === 'pending'
                        ? 'Your build request is in the queue and will be processed soon.'
                        : 'Your build is currently being processed. This may take several minutes.',
                    'build_id' => $existingBuild->id,
                    'status' => $existingBuild->status,
                    'existing' => true,
                ]);
            }

            // Create a new build record
            $build = $this->androidBuildService->requestBuild($user, $game, $version, true);

            // Dispatch the job to process the build
            Log::info('Dispatching Android build job', [
                'build_id' => $build->id,
                'queue_connection' => config('queue.default'),
                'queue_name' => 'android',
            ]);

            try {
                $job = new ProcessAndroidBuild($build);
                Log::info('Created job instance', [
                    'job_class' => get_class($job),
                    'build_id' => $build->id,
                ]);

                $dispatchedJob = dispatch($job->onQueue('android'));
                Log::info('Job dispatched', [
                    'dispatched_job' => $dispatchedJob ? 'success' : 'failed',
                    'build_id' => $build->id,
                ]);
            } catch (Exception $e) {
                Log::error('Error dispatching job', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => 'Android build requested successfully.',
                'build_id' => $build->id,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to request Android build', [
                'user_id' => $user->id,
                'game_id' => $game->id,
                'version_id' => $version?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to request Android build: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check the status of an Android build
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $request->validate([
            'build_id' => 'required|integer|exists:android_builds,id',
        ]);

        $user = Auth::user();
        $build = AndroidBuild::findOrFail($request->build_id);

        // Ensure the build belongs to the user
        if ($build->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this build.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'status' => $build->status,
            'message' => $this->getStatusMessage($build),
            'download_url' => $build->isCompleted() ? $this->androidBuildService->getDownloadUrl($build) : null,
        ]);
    }

    /**
     * Check if there's an existing completed build for a game version
     */
    public function checkExistingBuild(Request $request): JsonResponse
    {
        $request->validate([
            'game_id' => 'required|integer|exists:games,id',
            'version_id' => 'nullable|integer|exists:game_versions,id',
        ]);

        $user = Auth::user();
        $game = Game::findOrFail($request->game_id);
        $versionId = $request->version_id;

        // If no version is specified, use the latest version
        if (! $versionId) {
            $version = $game->gameVersions()->where('is_latest', true)->first();
            if ($version) {
                $versionId = $version->id;
            }
        }

        // Check for an existing completed build
        $build = AndroidBuild::where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->where('game_version_id', $versionId)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if ($build) {
            return response()->json([
                'exists' => true,
                'build' => [
                    'id' => $build->id,
                    'status' => $build->status,
                    'created_at' => $build->created_at,
                    'completed_at' => $build->completed_at,
                    'download_url' => $this->androidBuildService->getDownloadUrl($build),
                ],
            ]);
        }

        return response()->json([
            'exists' => false,
        ]);
    }

    /**
     * Download an Android build
     */
    public function download(int $buildId): StreamedResponse|RedirectResponse
    {
        $user = Auth::user();
        $build = AndroidBuild::findOrFail($buildId);

        // Ensure the build belongs to the user
        if ($build->user_id !== $user->id) {
            return redirect()->route('user.android-builds.index')
                ->with('error', 'You do not have permission to download this build.');
        }

        // Ensure the build is completed
        if (! $build->isCompleted() || ! $build->build_path) {
            return redirect()->route('user.android-builds.index')
                ->with('error', 'This build is not yet available for download.');
        }

        // Get the file path
        $filePath = $build->build_path;
        if (! Storage::exists($filePath)) {
            return redirect()->route('user.android-builds.index')
                ->with('error', 'The build file could not be found.');
        }

        // Generate a filename for the download
        $filename = basename($filePath);

        // Stream the file
        return Storage::download($filePath, $filename, [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }

    /**
     * Get a human-readable status message for a build
     */
    private function getStatusMessage(AndroidBuild $build): string
    {
        return match ($build->status) {
            'pending' => 'Your build request is in the queue and will be processed soon.',
            'processing' => 'Your build is currently being processed. This may take several minutes.',
            'completed' => 'Your build is complete and ready for download.',
            'failed' => 'Your build failed: ' . ($build->error_message ?? 'Unknown error'),
            default => 'Unknown status',
        };
    }
}

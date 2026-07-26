<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RenpyAnalyzerDockerRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class RenpyAnalyzerController extends Controller
{
    public function analyze(Request $request, RenpyAnalyzerDockerRunner $runner): JsonResponse
    {
        if (! config('services.renpy.analyzer_server', false)) {
            abort(404);
        }

        $token = config('services.renpy.analyzer_token');
        $authorization = $request->header('Authorization', '');
        if (! is_string($token) || $token === '' || ! hash_equals("Bearer {$token}", $authorization)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'archive_path' => ['required', 'string'],
        ]);

        $archivePath = realpath($data['archive_path']);
        $allowedBasePath = realpath((string) config('services.renpy.analyzer_shared_path'));

        if (
            $archivePath === false
            || $allowedBasePath === false
            || ! str_starts_with($archivePath, rtrim($allowedBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
            || ! File::isFile($archivePath)
        ) {
            return response()->json(['message' => 'Archive path is not allowed'], 422);
        }

        // The stats document is handed back by path, not by value. Caller and
        // callee share this directory, and a large game's document is far too
        // big to serialize through an HTTP response without both sides holding
        // a full copy of it.
        $statsPath = dirname($archivePath) . '/stats-' . bin2hex(random_bytes(8)) . '.ndjson';

        if (! $runner->analyze($archivePath, $statsPath)) {
            // Deliberately generic: the runner's diagnostic can quote container
            // output and must not reach the response.
            return response()->json([
                'message' => 'No stats could be extracted',
            ], 422);
        }

        return response()->json(['stats_path' => $statsPath]);
    }
}

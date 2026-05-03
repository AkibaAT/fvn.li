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
            || ! str_starts_with($archivePath, rtrim($allowedBasePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || ! File::isFile($archivePath)
        ) {
            return response()->json(['message' => 'Archive path is not allowed'], 422);
        }

        $stats = $runner->analyze($archivePath);
        if ($stats === null) {
            return response()->json(['message' => 'No stats could be extracted'], 422);
        }

        return response()->json(['stats' => $stats]);
    }
}

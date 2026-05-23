<?php

declare(strict_types=1);

namespace App\Services;

use App\ValueObjects\Upload;

class GameUploadAnalyzer
{
    /**
     * @return array{
     *     seenUploads: array<int|string, mixed>,
     *     hasChanges: bool,
     *     candidateUploads: array<int, Upload>,
     *     processableUploads: array<int, Upload>,
     *     platforms: array{windows: bool, linux: bool, mac: bool, android: bool, web: bool}
     * }
     */
    public function analyze(array $uploads, array $seenUploads, bool $force): array
    {
        $hasChanges = false;
        $candidateUploads = [];
        $processableUploads = [];
        $platforms = [
            'windows' => false,
            'linux' => false,
            'mac' => false,
            'android' => false,
            'web' => false,
        ];

        foreach ($uploads as $upload) {
            $fileId = (int) $upload['id'];
            $currentFilename = $upload['filename'] ?? '';
            $currentDisplayName = $upload['display_name'] ?? null;
            $currentMd5 = $upload['md5_hash'] ?? null;
            $currentUpdatedAt = $upload['updated_at'];
            $currentBuildId = $upload['build_id'] ?? null;
            $currentBuild = $upload['build'] ?? [];
            $currentUserVersion = $currentBuild['user_version'] ?? null;
            $currentBuildUpdatedAt = $currentBuild['updated_at'] ?? null;

            $isNewOrChanged = (
                ! isset($seenUploads[$fileId]) ||
                ($seenUploads[$fileId]['filename'] ?? '') !== $currentFilename ||
                ($seenUploads[$fileId]['md5_hash'] ?? null) !== $currentMd5 ||
                ($seenUploads[$fileId]['updated_at'] ?? null) !== $currentUpdatedAt ||
                ($seenUploads[$fileId]['build_id'] ?? null) !== $currentBuildId ||
                ($seenUploads[$fileId]['build_updated_at'] ?? null) !== $currentBuildUpdatedAt
            );

            if ($isNewOrChanged || $force) {
                $hasChanges = true;
                $seenUploads[$fileId] = [
                    'display_name' => $currentDisplayName,
                    'md5_hash' => $currentMd5,
                    'updated_at' => $currentUpdatedAt,
                    'build_id' => $currentBuildId,
                    'build_updated_at' => $currentBuildUpdatedAt,
                    'user_version' => $currentUserVersion,
                    'filename' => $currentFilename,
                    'traits' => $upload['traits'] ?? [],
                    'type' => $upload['type'] ?? '',
                ];

                $candidateUpload = Upload::fromArray($seenUploads[$fileId], $fileId);
                if ($candidateUpload->isProcessable()) {
                    $candidateUploads[] = $candidateUpload;
                }
            }

            if (isset($seenUploads[$fileId])) {
                $currentUpload = Upload::fromArray($seenUploads[$fileId], $fileId);
                if ($currentUpload->isProcessable()) {
                    $processableUploads[] = $currentUpload;
                }
            }

            if (! empty($upload['traits'])) {
                $platforms['windows'] = $platforms['windows'] || in_array('p_windows', $upload['traits']);
                $platforms['linux'] = $platforms['linux'] || in_array('p_linux', $upload['traits']);
                $platforms['mac'] = $platforms['mac'] || in_array('p_osx', $upload['traits']);
                $platforms['android'] = $platforms['android'] || in_array('p_android', $upload['traits']);
            }
            if (($upload['type'] ?? '') === 'html') {
                $platforms['web'] = true;
            }
        }

        return [
            'seenUploads' => $seenUploads,
            'hasChanges' => $hasChanges,
            'candidateUploads' => $candidateUploads,
            'processableUploads' => $processableUploads,
            'platforms' => $platforms,
        ];
    }
}

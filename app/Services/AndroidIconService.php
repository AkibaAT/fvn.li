<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Traits\HandlesLocalImages;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class AndroidIconService
{
    use HandlesLocalImages;

    private ImageManager $imageManager;

    public function __construct(
        private readonly Client $httpClient,
        private readonly ImageDownloadUrlValidator $imageUrlValidator,
    ) {
        $this->imageManager = new ImageManager(Driver::class);
    }

    public function create(Game $game, string $gameDir): void
    {
        try {
            if (! $game->thumb_url) {
                Log::warning('Game has no thumbnail URL', [
                    'game_id' => $game->id,
                    'game_name' => $game->name,
                ]);

                return;
            }

            $thumbnailUrl = $game->getThumbnailUrl('default');

            Log::info('Creating Android icon from game thumbnail', [
                'game_id' => $game->id,
                'thumb_url' => $thumbnailUrl,
            ]);

            $tempDir = storage_path('app/temp/android_icon_'.$game->id);
            File::makeDirectory($tempDir, 0755, true, true);

            $tempFile = $this->stageThumbnail($thumbnailUrl, $tempDir);
            $foregroundPath = $gameDir.'/android-icon_foreground.png';
            $image = $this->imageManager->decodePath($tempFile);

            $width = $image->width();
            $height = $image->height();
            $cropSize = min($width, $height);
            $x = $width > $height ? intval(($width - $height) / 2) : 0;
            $y = $height > $width ? intval(($height - $width) / 2) : 0;

            $image = $image->crop($cropSize, $cropSize, $x, $y)->resize(512, 512);
            File::put($foregroundPath, $image->encode(new JpegEncoder));

            Log::info('Android icon created successfully', [
                'path' => $foregroundPath,
            ]);

            File::deleteDirectory($tempDir);
        } catch (Exception $e) {
            Log::error('Failed to create Android icon', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    private function stageThumbnail(string $thumbnailUrl, string $tempDir): string
    {
        if ($this->isLocalThumbnail($thumbnailUrl)) {
            $localPath = $this->getLocalThumbnailPath($thumbnailUrl);
            if (! $localPath || ! file_exists($localPath)) {
                throw new Exception('Local thumbnail not found: '.$localPath);
            }

            $tempFile = $tempDir.'/thumbnail'.pathinfo($localPath, PATHINFO_EXTENSION);
            copy($localPath, $tempFile);

            return $tempFile;
        }

        $tempFile = $tempDir.'/thumbnail.jpg';
        $response = $this->httpClient->get($this->imageUrlValidator->validate($thumbnailUrl), [
            'timeout' => 30,
            'connect_timeout' => 10,
            'allow_redirects' => false,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception("Failed to download thumbnail: HTTP {$response->getStatusCode()}");
        }

        $content = $response->getBody()->getContents();
        if (empty($content)) {
            throw new Exception('Downloaded content is empty');
        }

        File::put($tempFile, $content);
        if (! File::exists($tempFile) || File::size($tempFile) === 0) {
            throw new Exception('Failed to save downloaded content');
        }

        return $tempFile;
    }
}

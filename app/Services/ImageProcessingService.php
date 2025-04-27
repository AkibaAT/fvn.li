<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageProcessingService
{
    private readonly ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver);
    }

    /**
     * Process an image variant while maintaining aspect ratio
     *
     * @param  string  $sourcePath  Path to the source image
     * @param  string  $targetPath  Path where the processed image should be saved
     * @param  array  $config  Configuration with width and height
     * @param  int  $quality  WebP quality (0-100)
     * @param  string  $diskName  Storage disk name
     *
     * @throws Exception
     */
    public function processImageVariant(
        string $sourcePath,
        string $targetPath,
        array $config,
        int $quality,
        string $diskName = 'public'
    ): void {
        try {
            // For GIFs, first extract the first frame using ImageMagick to reduce memory usage
            $imageInfo = getimagesize($sourcePath);
            $mimeType = $imageInfo['mime'];

            if ($mimeType === 'image/gif') {
                $tempJpg = tempnam(sys_get_temp_dir(), 'image_frame_');
                // Extract first frame and convert to JPG
                $command = sprintf(
                    'convert %s[0] -background white -flatten %s',
                    escapeshellarg($sourcePath),
                    escapeshellarg($tempJpg)
                );
                exec($command, $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new Exception('Failed to extract first frame from GIF');
                }

                // Use the extracted frame as source
                $sourcePath = $tempJpg;
            }

            try {
                // Load source image
                $image = $this->imageManager->read($sourcePath);

                // Verify we got a valid image
                if ($image->width() === 0 || $image->height() === 0) {
                    throw new Exception('Invalid image dimensions');
                }

                // Special handling for the "large" variant - keep original resolution
                $variantName = basename(dirname($targetPath)) === 'screenshots' ?
                    basename($targetPath, '.webp') : '';

                // Check if this is the large variant (ends with _large.webp)
                $isLargeVariant = str_ends_with($variantName, '_large');

                // For large variant, keep the original resolution, just convert to WebP
                if ($isLargeVariant) {
                    // No resizing for large variant - keep original resolution
                } else {
                    // For other variants, scale to fit within target dimensions
                    $widthRatio = $config['width'] / $image->width();
                    $heightRatio = $config['height'] / $image->height();

                    // Use the smaller ratio to ensure the image fits within the target dimensions
                    $ratio = min($widthRatio, $heightRatio);

                    $newWidth = intval($image->width() * $ratio);
                    $newHeight = intval($image->height() * $ratio);

                    // Resize the image while maintaining aspect ratio
                    $image->resize($newWidth, $newHeight);
                }

                // Save as WebP
                Storage::disk($diskName)->put(
                    $targetPath,
                    (string) $image->toWebp($quality)
                );
            } finally {
                // Clean up temporary frame file if it exists
                if (isset($tempJpg) && file_exists($tempJpg)) {
                    unlink($tempJpg);
                }
            }
        } catch (Exception $e) {
            throw new Exception("Failed to process image: {$e->getMessage()}");
        }
    }

    /**
     * Get dimensions of an image using ImageMagick's identify command
     *
     * @param  string  $path  Path to the image
     * @param  string  $diskName  Storage disk name
     * @return array Array with width and height
     *
     * @throws Exception
     */
    public function getImageDimensions(string $path, string $diskName = 'public'): array
    {
        $realPath = Storage::disk($diskName)->path($path);

        // Use [0] to specify first frame, which works for both animated and static images
        $command = sprintf('identify -format "%%wx%%h" %s[0]', escapeshellarg($realPath));
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || empty($output)) {
            throw new Exception('Failed to get image dimensions');
        }

        // identify outputs dimensions in format "widthxheight"
        [$width, $height] = explode('x', $output[0]);

        return [
            'width' => (int) $width,
            'height' => (int) $height,
        ];
    }
}

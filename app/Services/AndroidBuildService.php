<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AndroidBuild;
use App\Models\Game;
use App\Models\GameVersion;
use App\Traits\HandlesLocalImages;
use App\ValueObjects\Upload;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;
use Symfony\Component\Process\Process as SymfonyProcess;
use ZipArchive;

class AndroidBuildService
{
    use HandlesLocalImages;

    private ImageManager $imageManager;

    public function __construct(
        private readonly GameArchiveService $archiveService,
        private readonly Client $httpClient
    ) {
        // Initialize ImageManager with GD driver using fully qualified class name
        $this->imageManager = new ImageManager(Driver::class);
    }

    /**
     * Request an Android build for a game
     *
     * @param  Authenticatable  $user
     * @param  bool  $createNew  Whether to create a new build record or just check for existing ones
     *
     * @throws Exception
     */
    public function requestBuild($user, Game $game, ?GameVersion $version = null, bool $createNew = true): ?AndroidBuild
    {
        // If no version is specified, use the latest version
        if (! $version) {
            $version = $game->gameVersions()->where('is_latest', true)->firstOrFail();
        }

        // Check eligibility
        if (! $this->isEligibleForAndroidBuild($game, $version)) {
            throw new Exception('This game is not eligible for Android builds.');
        }

        // First check if there's already a completed build for this game version
        $completedBuild = AndroidBuild::where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->where('game_version_id', $version->id)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if ($completedBuild) {
            return $completedBuild;
        }

        // Then check if there's already a pending or processing build for this game version
        $existingBuild = AndroidBuild::where('user_id', $user->id)
            ->where('game_id', $game->id)
            ->where('game_version_id', $version->id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingBuild) {
            return $existingBuild;
        }

        // Only create a new build if requested
        if (! $createNew) {
            return null;
        }

        // Create a new build request
        return AndroidBuild::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'game_version_id' => $version->id,
            'status' => 'pending',
            'build_id' => Str::uuid(),
        ]);
    }

    /**
     * Check if a game is eligible for Android builds
     */
    public function isEligibleForAndroidBuild(Game $game, ?GameVersion $version = null): bool
    {
        // Must be a Ren'Py game
        if ($game->game_engine !== "Ren'Py") {
            return false;
        }

        // If a specific version is provided, check if it already has Android support
        if ($version && $version->is_android) {
            return false;
        }

        // If no specific version, check if the latest version has Android support
        if (! $version && $game->gameVersions()->where('is_latest', true)->where('is_android', true)->exists()) {
            return false;
        }

        // Check if the Ren'Py SDK is configured
        $sdkPath = config('services.renpy.sdk_path');
        if (! $sdkPath || ! File::exists($sdkPath . '/renpy.sh')) {
            return false;
        }

        // Check if the Android SDK is configured within Ren'Py
        if (! File::exists($sdkPath . '/rapt')) {
            return false;
        }

        return true;
    }

    /**
     * Process an Android build
     *
     * @throws Exception
     */
    public function processBuild(AndroidBuild $build): bool
    {
        try {
            // Update build status
            $build->status = 'processing';
            $build->save();

            // Get the game and version
            $game = $build->game;
            $version = $build->gameVersion;

            // Get or create a keystore for this game
            $keystorePath = $this->getOrCreateKeystore($game);

            // Store the keystore path in the build record
            $build->keystore_path = $keystorePath;
            $build->save();

            // Get the archive path
            $archivePath = $this->archiveService->getStoredArchive($game->id, $version->id);
            if (! $archivePath) {
                Log::info('No local archive found, checking uploads in database', [
                    'game_id' => $game->id,
                    'version_id' => $version->id,
                    'game_name' => $game->name,
                ]);

                // Get uploads directly from the database
                $uploads = $game->uploads ?? [];

                // Log the raw uploads data for debugging
                Log::info('Raw uploads data from database', [
                    'game_id' => $game->id,
                    'game_name' => $game->name,
                    'uploads_type' => gettype($uploads),
                    'is_array' => is_array($uploads),
                    'is_null' => is_null($uploads),
                    'is_empty' => empty($uploads),
                ]);

                // If uploads is a string, try to decode it as JSON
                if (is_string($uploads) && ! empty($uploads)) {
                    try {
                        $decodedUploads = json_decode($uploads, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedUploads)) {
                            $uploads = $decodedUploads;
                            Log::info('Successfully decoded uploads JSON string', [
                                'count' => count($uploads),
                            ]);
                        }
                    } catch (Exception $e) {
                        Log::warning('Error decoding uploads JSON string', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // If uploads is not an array, try to convert it
                if (! is_array($uploads) && ! is_null($uploads)) {
                    try {
                        if (is_string($uploads)) {
                            // Try to decode JSON string
                            $decodedUploads = json_decode($uploads, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedUploads)) {
                                $uploads = $decodedUploads;
                                Log::info('Successfully converted uploads string to array', [
                                    'count' => count($uploads),
                                ]);
                            } else {
                                Log::warning('Failed to decode uploads JSON string', [
                                    'json_error' => json_last_error_msg(),
                                ]);
                            }
                        } else {
                            // Try to convert to array
                            $uploads = (array) $uploads;
                            Log::info('Converted uploads to array', [
                                'count' => count($uploads),
                            ]);
                        }
                    } catch (Exception $e) {
                        Log::error('Error converting uploads to array', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Ensure uploads is an array
                if (! is_array($uploads)) {
                    $uploads = [];
                }

                // If we still don't have any uploads, we can't proceed
                if (empty($uploads)) {
                    Log::error('No uploads found for game', [
                        'game_id' => $game->id,
                        'game_name' => $game->name,
                        'game_url' => $game->url,
                    ]);

                    throw new Exception('No uploads found for this game. Please ensure the game has downloadable files on itch.io.');
                }

                Log::info('Available uploads for game', [
                    'game_id' => $game->id,
                    'uploads_count' => count($uploads),
                    'uploads' => array_map(function ($upload) {
                        return [
                            'id' => $upload['id'] ?? 'unknown',
                            'filename' => $upload['filename'] ?? 'unknown',
                            'size' => $upload['size'] ?? 'unknown',
                        ];
                    }, $uploads),
                ]);

                // Convert the raw uploads array to Upload objects
                $uploadObjects = collect();
                foreach ($uploads as $key => $upload) {
                    // If the upload doesn't have an ID but the key is numeric, use the key as the ID
                    if ((! isset($upload['id']) || $upload['id'] === 'unknown') && is_numeric($key)) {
                        $id = (int) $key;
                    } else {
                        $id = $upload['id'] ?? 0;
                    }

                    // Make sure we have the required fields
                    $upload['updated_at'] = $upload['updated_at'] ?? date('Y-m-d H:i:s');

                    try {
                        $uploadObj = Upload::fromArray($upload, $id);
                        $uploadObjects->push($uploadObj);
                    } catch (Exception $e) {
                        Log::warning('Failed to create Upload object', [
                            'error' => $e->getMessage(),
                            'upload' => $upload,
                        ]);
                    }
                }

                // Get the best upload using the Upload class
                $bestUpload = Upload::getBest($uploadObjects);

                if (! $bestUpload) {
                    throw new Exception('Could not find a suitable upload for this game version. Please ensure the game has downloadable files on itch.io.');
                }

                Log::info('Selected best upload', [
                    'upload_id' => $bestUpload->id,
                    'filename' => $bestUpload->filename,
                    'traits' => $bestUpload->traits,
                    'is_linux' => $bestUpload->isLinux(),
                    'is_windows' => $bestUpload->isWindows(),
                    'is_mac' => $bestUpload->isMac(),
                ]);

                // Download the archive
                $result = $this->archiveService->downloadAndProcess(
                    $game->url,
                    $bestUpload->filename,
                    $bestUpload->id,
                    $game->id,
                    $version->id
                );

                $archivePath = $result['archive'];

                if (! $archivePath) {
                    throw new Exception('Failed to download and process the game archive.');
                }

                Log::info('Successfully downloaded and processed game archive', [
                    'game_id' => $game->id,
                    'version_id' => $version->id,
                    'archive_path' => $archivePath,
                ]);
            }

            // Create a temporary directory for extraction
            $extractPath = storage_path('app/temp/android_build_' . $build->build_id);
            File::makeDirectory($extractPath, 0755, true);

            try {
                // Extract the archive
                $this->extractArchive($archivePath, $extractPath);

                // Find the game directory
                $gameDir = $this->findGameDirectory($extractPath);
                if (! $gameDir) {
                    throw new Exception('Could not find the game directory in the archive.');
                }

                // Build the Android APK
                $apkPath = $this->buildAndroidApk($gameDir, $game, $version, $build);

                // Store the APK
                $storagePath = $this->storeApk($apkPath, $game, $version, $build);

                // Update build status
                $build->status = 'completed';
                $build->build_path = $storagePath;
                $build->completed_at = now();
                $build->save();

                return true;
            } finally {
                // Clean up
                if (File::exists($extractPath)) {
                    File::deleteDirectory($extractPath);
                }
            }
        } catch (Exception $e) {
            // Log the error
            Log::error('Android build failed', [
                'build_id' => $build->id,
                'game_id' => $build->game_id,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            // Update build status
            $build->status = 'failed';
            $build->error_message = $e->getMessage();
            $build->save();

            throw $e;
        }
    }

    /**
     * Get the download URL for a completed build
     */
    public function getDownloadUrl(AndroidBuild $build): ?string
    {
        if ($build->status !== 'completed' || ! $build->build_path) {
            return null;
        }

        return Storage::url($build->build_path);
    }

    /**
     * Get an existing keystore for this game or create a new one
     */
    private function getOrCreateKeystore(Game $game): string
    {
        $keystoreDir = storage_path('app/keystores');
        File::makeDirectory($keystoreDir, 0755, true, true);

        $keystorePath = $keystoreDir . '/' . $game->id . '.keystore';

        // If keystore already exists, return it
        if (File::exists($keystorePath)) {
            Log::info('Using existing keystore for game', [
                'game_id' => $game->id,
                'keystore_path' => $keystorePath,
            ]);

            return $keystorePath;
        }

        Log::info('Creating new keystore for game', [
            'game_id' => $game->id,
            'keystore_path' => $keystorePath,
        ]);

        try {
            // Use the correct command for generating a keystore with empty passwords
            $process = new SymfonyProcess([
                'keytool',
                '-genkey',
                '-v',
                '-keystore', $keystorePath,
                '-alias', 'android',
                '-keyalg', 'RSA',
                '-keysize', '2048',
                '-keypass', 'android',
                '-storepass', 'android',
                '-validity', '20000',
                '-dname', "CN={$game->name}, OU=FVN.li, O=FVN.li, L=Unknown, ST=Unknown, C=AT",
            ]);

            $process->setTimeout(60); // Give it a minute to run
            $process->run();

            if (! $process->isSuccessful()) {
                $output = $process->getOutput();
                $errorOutput = $process->getErrorOutput();
                $exitCode = $process->getExitCode();

                Log::error('Failed to generate keystore with keytool', [
                    'output' => $output,
                    'error_output' => $errorOutput,
                    'exit_code' => $exitCode,
                    'game_id' => $game->id,
                ]);

                // Fall back to creating a dummy keystore
                Log::info('Falling back to dummy keystore for game', [
                    'game_id' => $game->id,
                ]);

                // Create a simple keystore file with some random data
                $keystoreData = random_bytes(2048); // Generate some random data
                File::put($keystorePath, $keystoreData);
            } else {
                Log::info('Successfully created keystore with keytool', [
                    'game_id' => $game->id,
                ]);
            }

            return $keystorePath;
        } catch (Exception $e) {
            Log::error('Failed to create keystore', [
                'error' => $e->getMessage(),
                'game_id' => $game->id,
            ]);

            // Fall back to creating a dummy keystore
            try {
                Log::info('Falling back to dummy keystore after exception', [
                    'game_id' => $game->id,
                ]);

                // Create a simple keystore file with some random data
                $keystoreData = random_bytes(2048); // Generate some random data
                File::put($keystorePath, $keystoreData);

                return $keystorePath;
            } catch (Exception $innerException) {
                throw new RuntimeException('Failed to create keystore: ' . $e->getMessage() . ' and fallback also failed: ' . $innerException->getMessage());
            }
        }
    }

    /**
     * Extract the game archive
     */
    private function extractArchive(string $archivePath, string $extractPath): void
    {
        // Use the appropriate extraction method based on the file extension
        $extension = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));

        if ($extension === 'zip') {
            $zip = new ZipArchive;
            if ($zip->open($archivePath) === true) {
                $zip->extractTo($extractPath);
                $zip->close();
            } else {
                throw new RuntimeException("Failed to extract ZIP archive: {$archivePath}");
            }
        } elseif ($extension === 'gz' || $extension === 'bz2') {
            // For tar.gz or tar.bz2 files
            $process = new SymfonyProcess(['tar', '-xf', $archivePath, '-C', $extractPath]);
            $process->setTimeout(300); // 5 minute timeout
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException("Failed to extract TAR archive: {$archivePath}");
            }
        } else {
            throw new RuntimeException("Unsupported archive format: {$extension}");
        }
    }

    /**
     * Find the game directory in the extracted archive
     */
    private function findGameDirectory(string $extractPath): ?string
    {
        // Look for common Ren'Py game indicators
        $indicators = [
            'game/script.rpy',
            'game/script.rpyc',
            'renpy/common',
        ];

        // First, check if the extract path itself is the game directory
        foreach ($indicators as $indicator) {
            if (File::exists("{$extractPath}/{$indicator}")) {
                return $extractPath;
            }
        }

        // If not, look for a subdirectory that might be the game directory
        $directories = File::directories($extractPath);
        foreach ($directories as $directory) {
            foreach ($indicators as $indicator) {
                if (File::exists("{$directory}/{$indicator}")) {
                    return $directory;
                }
            }
        }

        return null;
    }

    /**
     * Build the Android APK using the Ren'Py SDK
     */
    private function buildAndroidApk(string $gameDir, Game $game, GameVersion $version, AndroidBuild $build): string
    {
        $sdkPath = config('services.renpy.sdk_path');

        // Prepare a clean package name based on the game slug
        $packageName = 'li.fvn.' . preg_replace('/[^a-z0-9]/', '', strtolower($game->slug));

        // Create a temporary directory for the build output
        $buildOutputDir = storage_path('app/temp/android_output_' . $build->build_id);
        File::makeDirectory($buildOutputDir, 0755, true);

        // No need for a separate build directory, we'll use the game directory directly

        try {
            // Create a basic configuration file for the Android build
            $configPath = $gameDir . '/android.json';
            $config = [
                'package' => $packageName,
                'name' => $game->name,
                'icon_name' => $game->name,
                'version' => $version->version,
                'numeric_version' => $this->convertVersionToNumeric($version->version),
                'orientation' => 'sensorLandscape',
                'permissions' => ['VIBRATE'],
                'include_pil' => false,
                'include_sqlite' => false,
                'layout' => null,
                'source' => false,
                'expansion' => false,
                'google_play_key' => null,
                'google_play_salt' => null,
                'store' => 'none',
                'update_icons' => true,
                'update_always' => true,
                'update_keystores' => true,
                'heap_size' => 3,
            ];

            File::put($configPath, json_encode($config, JSON_PRETTY_PRINT));

            // Get or create a keystore for this game
            $keystorePath = $this->getOrCreateKeystore($game);

            // Copy the keystore to the game directory with the filename android.keystore
            $androidKeystorePath = $gameDir . '/android.keystore';
            File::copy($keystorePath, $androidKeystorePath);

            Log::info('Copied keystore to game directory', [
                'source' => $keystorePath,
                'destination' => $androidKeystorePath,
            ]);

            File::copy(resource_path('renpy/android-presplash.jpg'), $gameDir . '/android-presplash.jpg');

            // Create Android icon from game thumbnail
            $this->createAndroidIcon($game, $gameDir);

            // Create output directory for the APK
            $outputDir = storage_path('app/temp/android_build_output_' . $build->id);
            File::makeDirectory($outputDir, 0755, true, true);

            // Run the Ren'Py Android build command with signing
            $process = new SymfonyProcess([
                $sdkPath . '/renpy.sh',
                'launcher',
                'android_build',
                '--destination',
                $outputDir,
                $gameDir,
            ]);

            // Log the command we're about to run
            Log::info('Running Ren\'Py Android build command', [
                'command' => $process->getCommandLine(),
                'working_directory' => $sdkPath,
            ]);

            $process->setTimeout(1800); // 30 minute timeout
            $process->setWorkingDirectory($sdkPath);

            // Run the process and capture output in real-time for logging
            $process->run(function ($type, $buffer) {
                if ($type === SymfonyProcess::OUT) {
                    Log::info('Android build output: ' . $buffer);
                } else { // SymfonyProcess::ERR
                    Log::warning('Android build error: ' . $buffer);
                }
            });

            if (! $process->isSuccessful()) {
                Log::error('Android build process failed', [
                    'command' => $process->getCommandLine(),
                    'output' => $process->getOutput(),
                    'error_output' => $process->getErrorOutput(),
                    'exit_code' => $process->getExitCode(),
                ]);

                throw new RuntimeException('Failed to build Android APK: ' . $process->getErrorOutput());
            }

            Log::info('Android build process completed successfully');

            // Find the generated APK file in the output directory
            $apkPath = null;

            // Look for APK files in the output directory
            $files = File::glob($outputDir . '/*.apk');

            if (! empty($files)) {
                // Use the first APK file found
                $apkPath = $files[0];
                Log::info('Found APK file', [
                    'path' => $apkPath,
                    'all_files' => $files,
                ]);
            }

            if (! $apkPath || ! File::exists($apkPath)) {
                throw new RuntimeException('APK file not found after build in output directory: ' . $outputDir);
            }

            return $apkPath;
        } finally {
            // Clean up
            if (File::exists($buildOutputDir)) {
                File::deleteDirectory($buildOutputDir);
            }
        }
    }

    /**
     * Convert a version string to a numeric version code for Android
     *
     * Takes a version string like "0.4.1" and converts it to a numeric version
     * by removing all non-numeric characters. Falls back to 1 if no numbers are found.
     */
    private function convertVersionToNumeric(string $version): int
    {
        // Extract all numeric characters from the version string
        $numericVersion = preg_replace('/[^0-9]/', '', $version);

        // If no numeric characters were found, return 1 as a fallback
        if (empty($numericVersion)) {
            return 1;
        }

        // Convert to integer and return
        return (int) $numericVersion;
    }

    /**
     * Create Android icon from game thumbnail
     */
    private function createAndroidIcon(Game $game, string $gameDir): void
    {
        try {
            if (! $game->thumb_url) {
                Log::warning('Game has no thumbnail URL', [
                    'game_id' => $game->id,
                    'game_name' => $game->name,
                ]);

                return;
            }

            // Use the same thumbnail logic as the frontend
            $thumbnailUrl = $game->getThumbnailUrl('default');

            Log::info('Creating Android icon from game thumbnail', [
                'game_id' => $game->id,
                'thumb_url' => $thumbnailUrl,
            ]);

            // Create temporary directory
            $tempDir = storage_path('app/temp/android_icon_' . $game->id);
            File::makeDirectory($tempDir, 0755, true, true);

            // Handle local vs external thumbnails
            if ($this->isLocalThumbnail($thumbnailUrl)) {
                // Copy local cached thumbnail directly
                $localPath = $this->getLocalThumbnailPath($thumbnailUrl);
                if ($localPath && file_exists($localPath)) {
                    $tempFile = $tempDir . '/thumbnail' . pathinfo($localPath, PATHINFO_EXTENSION);
                    copy($localPath, $tempFile);
                } else {
                    throw new Exception('Local thumbnail not found: ' . $localPath);
                }
            } else {
                // Download external thumbnail
                $tempFile = $tempDir . '/thumbnail.jpg';
                $response = $this->httpClient->get($thumbnailUrl, [
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'verify' => false,
                ]);

                if ($response->getStatusCode() !== 200) {
                    throw new Exception("Failed to download thumbnail: HTTP {$response->getStatusCode()}");
                }

                // Save the thumbnail to a temporary file
                $content = $response->getBody()->getContents();
                if (empty($content)) {
                    throw new Exception('Downloaded content is empty');
                }

                File::put($tempFile, $content);

                // Verify the downloaded file
                if (! File::exists($tempFile) || File::size($tempFile) === 0) {
                    throw new Exception('Failed to save downloaded content');
                }
            }

            // Create the foreground icon in the game directory
            $foregroundPath = $gameDir . '/android-icon_foreground.png';

            // Load and process the image
            $image = $this->imageManager->decodePath($tempFile);

            // Resize to a square (512x512 is a good size for Android icons)
            $size = 512;

            // Determine the crop dimensions to make it square
            $width = $image->width();
            $height = $image->height();

            if ($width > $height) {
                // Landscape image
                $cropSize = $height;
                $x = intval(($width - $height) / 2);
                $y = 0;
            } else {
                // Portrait or square image
                $cropSize = $width;
                $x = 0;
                $y = intval(($height - $width) / 2);
            }

            // Crop to square
            $image = $image->crop($cropSize, $cropSize, $x, $y);

            // Resize to target size
            $image = $image->resize($size, $size);

            // Save as JPEG (Ren'Py will convert it to the appropriate format)
            $encodedImage = $image->encode(new JpegEncoder);
            File::put($foregroundPath, $encodedImage);

            Log::info('Android icon created successfully', [
                'path' => $foregroundPath,
            ]);

            // Clean up
            File::deleteDirectory($tempDir);
        } catch (Exception $e) {
            Log::error('Failed to create Android icon', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    /**
     * Store the APK file
     *
     * @param  AndroidBuild  $build  The build parameter is not used but kept for future tracking purposes
     */
    private function storeApk(string $apkPath, Game $game, GameVersion $version, AndroidBuild $build): string
    {
        // @phpstan-ignore-next-line

        $storagePath = "public/android_builds/{$game->id}/{$version->id}";
        $filename = "fvn-li-{$game->slug}-{$version->version}.apk";

        // Ensure directory exists
        Storage::makeDirectory($storagePath);

        // Store the APK
        Storage::putFileAs($storagePath, $apkPath, $filename);

        return "{$storagePath}/{$filename}";
    }
}

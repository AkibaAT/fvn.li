<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameJam;
use Dom\HTMLDocument;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ItchGameMetadataExtractor
{
    public function checkForDemo(Game $game, HTMLDocument $doc): void
    {
        $game->has_demo = false;
        if (! $game->is_paid) {
            return;
        }

        $playButton = $doc->querySelector('.play_btn');
        $playInBrowser = $doc->querySelector('a[data-action="play_in_browser"]');
        $hasBrowserPlayable = ($playButton !== null || $playInBrowser !== null);

        $hasFreeDownload = false;
        if (! empty($game->uploads)) {
            foreach ($game->uploads as $uploadData) {
                $filename = strtolower($uploadData['filename'] ?? '');
                $displayName = strtolower($uploadData['display_name'] ?? '');

                $isDemoFile = str_contains($filename, 'demo') ||
                    str_contains($displayName, 'demo') ||
                    str_contains($filename, 'trial') ||
                    str_contains($displayName, 'trial') ||
                    str_contains($filename, 'sample') ||
                    str_contains($displayName, 'sample');

                $isFreeDownload = false;
                $isDemo = false;
                if (isset($uploadData['traits']) && is_array($uploadData['traits'])) {
                    $isFreeDownload = in_array('p_free', $uploadData['traits']);
                    $isDemo = in_array('demo', $uploadData['traits']);
                }

                if ($isDemoFile || $isFreeDownload || $isDemo) {
                    $hasFreeDownload = true;
                    break;
                }
            }
        }

        $game->has_demo = ($hasBrowserPlayable || $hasFreeDownload);

        Log::info("Demo detection for {$game->name}", [
            'game_id' => $game->id,
            'has_demo' => $game->has_demo,
            'browser_playable' => $hasBrowserPlayable,
            'free_download' => $hasFreeDownload,
            'uploads_count' => count($game->uploads ?? []),
            'uploads' => array_map(function ($upload) {
                $traits = $upload['traits'] ?? [];
                $isDemo = is_array($traits) && in_array('demo', $traits);
                $isFree = is_array($traits) && in_array('p_free', $traits);

                return [
                    'filename' => $upload['filename'] ?? '',
                    'display_name' => $upload['display_name'] ?? '',
                    'traits' => $traits,
                    'is_demo' => $isDemo,
                    'is_free' => $isFree,
                ];
            }, $game->uploads ?? []),
        ]);
    }

    public function extractFullDescription(Game $game, HTMLDocument $doc, ItchHtmlProcessor $htmlProcessor): void
    {
        $descriptionElement = $doc->querySelector('.formatted_description');
        if ($descriptionElement) {
            $htmlContent = $descriptionElement->innerHTML;
            $processedHtml = $htmlProcessor->process($htmlContent);
            $game->full_description = app(HtmlSanitizerService::class)->sanitizeDescription($processedHtml);
            if (empty($game->description)) {
                $game->description = trim(strip_tags($game->full_description ?? ''));
            }
        }
    }

    public function extractScreenshots(Game $game, HTMLDocument $doc): void
    {
        $screenshots = [];
        $existingScreenshotsByUrl = collect($game->screenshots ?? [])
            ->filter(fn ($screenshot) => isset($screenshot['url']))
            ->keyBy('url');

        $carousel = $doc->querySelector('.screenshot_list');
        if ($carousel) {
            $screenshotElements = $carousel->querySelectorAll('a.screenshot_link');
            if (count($screenshotElements) === 0) {
                $screenshotElements = $carousel->querySelectorAll('a[data-image_lightbox="true"]');
            }
            if (count($screenshotElements) === 0) {
                $screenshotElements = $carousel->querySelectorAll('a');
            }
            foreach ($screenshotElements as $element) {
                $imageUrl = $element->getAttribute('href');
                if ($imageUrl) {
                    $thumbnailElement = $element->querySelector('img');
                    $thumbnailUrl = $thumbnailElement ? $thumbnailElement->getAttribute('src') : $imageUrl;
                    if (! preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $imageUrl)) {
                        continue;
                    }
                    $screenshot = [
                        'url' => $imageUrl,
                        'thumbnail_url' => $thumbnailUrl,
                    ];

                    $existingScreenshot = $existingScreenshotsByUrl->get($imageUrl);
                    if (! empty($existingScreenshot['optimized'])) {
                        $screenshot['optimized'] = $existingScreenshot['optimized'];
                    }

                    $screenshots[] = $screenshot;
                }
            }
        }
        if (! empty($screenshots)) {
            $this->cleanupRemovedScreenshots($game, $screenshots);
            $game->screenshots = $screenshots;
        }
    }

    public function extractCustomCss(Game $game, string $html, ItchCssProcessor $cssProcessor): void
    {
        $customCss = '';
        if (preg_match('/<style[^>]*id="game_theme"[^>]*>([\s\S]*?)<\/style>/i', $html, $matches)) {
            $customCss .= trim($matches[1])."\n\n";
            Log::info('Found game theme CSS', ['css' => $matches[1]]);
        }
        if (preg_match('/<style[^>]*id="custom_css"[^>]*>([\s\S]*?)<\/style>/i', $html, $matches)) {
            $customCss .= trim($matches[1]);
            Log::info('Found custom CSS', ['css' => $matches[1]]);
        }
        if (! empty($customCss)) {
            $processedCss = $cssProcessor->process($customCss);
            if (! empty($processedCss)) {
                $customCss = $processedCss;
                Log::info('Processed and scoped CSS', ['css' => $customCss]);
            } else {
                $customCss = null;
                Log::info('CSS processing resulted in empty CSS');
            }
        } else {
            $customCss = null;
            Log::info('No CSS found in HTML');
        }
        Log::info('Setting custom_css attribute', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'css_length' => strlen($customCss ?? ''),
            'css_value' => $customCss,
        ]);
        $game->custom_css = $customCss;
    }

    public function extractGameJamInfo(Game $game, HTMLDocument $doc): void
    {
        $jamUrl = null;
        $jamName = null;
        $jamSection = $doc->querySelector('.game_jam_info');
        if ($jamSection) {
            $jamLink = $jamSection->querySelector('a');
            if ($jamLink) {
                $jamUrl = $jamLink->getAttribute('href');
                $jamName = trim($jamLink->textContent);
            }
        }
        if (! $jamUrl) {
            $jamLinks = $doc->querySelectorAll('a[href*="/jam/"]');
            foreach ($jamLinks as $link) {
                $href = $link->getAttribute('href');
                $text = trim($link->textContent);
                if (str_starts_with($text, 'Submission to') || str_contains($href, '/rate/') || str_contains($href, '/jam/')) {
                    if (str_starts_with($text, 'Submission to')) {
                        $jamName = str_replace('Submission to ', '', $text);
                    } else {
                        $titleElement = $doc->querySelector('title');
                        if ($titleElement) {
                            $pageTitle = trim($titleElement->textContent);
                            $jamName = preg_replace('/ - itch\\.io$/', '', $pageTitle);
                        } else {
                            $urlParts = explode('/', $href);
                            $slug = end($urlParts);
                            $jamName = str_replace('-', ' ', $slug);
                            $jamName = ucwords($jamName);
                        }
                    }
                    if (preg_match('|(https?://[^/]+/jam/[^/]+)/rate/|', $href, $matches)) {
                        $jamUrl = $matches[1];
                    } elseif (str_starts_with($href, 'http')) {
                        $jamUrl = $href;
                    } else {
                        $jamUrl = 'https://itch.io'.$href;
                    }
                    break;
                }
            }
        }
        if (empty($jamUrl) || empty($jamName)) {
            return;
        }
        try {
            $safeJamUrl = GameJam::normalizeAndValidateJamUrl($jamUrl);
            $gameJam = GameJam::findOrCreateFromUrl($safeJamUrl, $jamName);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Discarded non-itch game jam URL', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'jam_url' => $jamUrl,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $pendingJams = $game->pendingGameJamId ?? [];
        if (! in_array($gameJam->id, $pendingJams)) {
            $pendingJams[] = $gameJam->id;
            $game->pendingGameJamId = $pendingJams;
        }
        Log::info('Found game jam for game', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'game_exists' => $game->exists,
            'jam_id' => $gameJam->id,
            'jam_name' => $gameJam->name,
            'pending_association' => true,
        ]);
    }

    private function cleanupRemovedScreenshots(Game $game, array $newScreenshots): void
    {
        if (empty($game->screenshots)) {
            return;
        }
        $currentUrls = collect($game->screenshots)->pluck('url')->toArray();
        $newUrls = collect($newScreenshots)->pluck('url')->toArray();
        foreach ($game->screenshots as $index => $currentScreenshot) {
            $shouldCleanup = false;
            if (! in_array($currentScreenshot['url'], $newUrls)) {
                $shouldCleanup = true;
            } elseif (isset($newScreenshots[$index]) && $newScreenshots[$index]['url'] !== $currentScreenshot['url']) {
                $shouldCleanup = true;
            }
            if ($shouldCleanup && isset($currentScreenshot['optimized']) && ! empty($currentScreenshot['optimized'])) {
                $this->cleanupScreenshotOptimizedImages($game, $currentScreenshot);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $screenshot
     */
    private function cleanupScreenshotOptimizedImages(Game $game, array $screenshot): void
    {
        try {
            if (($screenshot['optimized'] ?? null) === true) {
                foreach (Storage::disk('public')->files('screenshots') as $file) {
                    if (str_starts_with($file, "screenshots/{$game->id}_screenshot_")) {
                        Storage::disk('public')->delete($file);
                        Log::info('Cleaned up legacy optimized screenshot', [
                            'game_id' => $game->id,
                            'file' => $file,
                        ]);
                    }
                }

                return;
            }

            foreach ($screenshot['optimized'] ?? [] as $variant) {
                if (isset($variant['path'])) {
                    Storage::disk('public')->delete($variant['path']);
                    Log::info('Cleaned up optimized screenshot', [
                        'game_id' => $game->id,
                        'file' => $variant['path'],
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to cleanup optimized screenshot images', [
                'game_id' => $game->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

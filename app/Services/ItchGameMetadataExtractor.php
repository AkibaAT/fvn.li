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
    /**
     * Extract price information from the game's HTML page
     *
     * @param  Game  $game  The game to extract price information for
     * @param  HTMLDocument  $doc  The parsed HTML document
     * @param  bool  $preserveApiPrice  If true, don't overwrite price if it was already set from API data
     */
    public function extractPriceInformation(Game $game, HTMLDocument $doc, bool $preserveApiPrice = false): void
    {
        $originalIsPaid = $game->is_paid;
        $originalMinPrice = $game->min_price;
        $originalIsOnSale = $game->is_on_sale;

        // If we should preserve API price and the game is marked as paid with a price > 0,
        // skip HTML price extraction as API data is more reliable
        if ($preserveApiPrice && $game->is_paid && $game->min_price > 0) {
            Log::info('Preserving price from API data (skipping HTML extraction)', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'min_price' => $game->min_price,
                'is_on_sale' => $game->is_on_sale,
                'sale_discount_percent' => $game->sale_discount_percent,
            ]);

            return;
        }

        $buySection = $doc->querySelector('.buy_game_section');
        if (! $buySection) {
            // Only update price to 0 if we're not preserving API price
            if (! $preserveApiPrice) {
                $game->min_price = 0;
                $game->is_on_sale = false;
                if (! $originalIsPaid) {
                    $game->is_paid = false;
                }
            }

            Log::info('Game appears free (no buy section)', [
                'game_id' => $game->id,
                'game_name' => $game->name,
                'original_is_paid' => $originalIsPaid,
                'new_is_paid' => $game->is_paid,
                'preserve_api_price' => $preserveApiPrice,
            ]);

            return;
        }

        $saleTag = $buySection->querySelector('.sale_tag');
        $game->is_on_sale = $saleTag !== null;

        $minPriceElement = $buySection->querySelector('.base_price');
        $game->currency = 'USD';
        if ($minPriceElement) {
            $priceText = trim($minPriceElement->textContent);
            preg_match('/\$?(\d+\.?\d*)/', $priceText, $matches);
            $game->min_price = $matches[1] ?? 0;
        } else {
            $game->min_price = 0;
        }

        if (! $originalIsPaid) {
            $game->is_paid = $game->min_price > 0;
        }

        Log::info('Extracted price information', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'original_min_price' => $originalMinPrice,
            'new_min_price' => $game->min_price,
            'original_is_on_sale' => $originalIsOnSale,
            'new_is_on_sale' => $game->is_on_sale,
            'original_is_paid' => $originalIsPaid,
            'new_is_paid' => $game->is_paid,
            'price_element_found' => $minPriceElement !== null,
            'price_text' => $minPriceElement ? trim($minPriceElement->textContent) : null,
            'preserve_api_price' => $preserveApiPrice,
        ]);
    }

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
            $game->full_description = $processedHtml;
            if (empty($game->description)) {
                $game->description = strip_tags($processedHtml);
            }
        }
    }

    public function extractScreenshots(Game $game, HTMLDocument $doc): void
    {
        $screenshots = [];
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
                    $screenshots[] = [
                        'url' => $imageUrl,
                        'thumbnail_url' => $thumbnailUrl,
                    ];
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
            $customCss .= trim($matches[1]) . "\n\n";
            Log::info('Found game theme CSS', ['css' => $matches[1]]);
        }
        if (preg_match('/<style[^>]*id="custom_css"[^>]*>([\s\S]*?)<\/style>/i', $html, $matches)) {
            $customCss .= trim($matches[1]);
            Log::info('Found custom CSS', ['css' => $matches[1]]);
        }
        if (! empty($customCss)) {
            $processedCss = $cssProcessor->process($customCss);
            if (! empty($processedCss)) {
                $customCss = ".game_description {\n" . $processedCss . "\n}";
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
                        $jamUrl = 'https://itch.io' . $href;
                    }
                    break;
                }
            }
        }
        if (empty($jamUrl) || empty($jamName)) {
            return;
        }
        $gameJam = GameJam::findOrCreateFromUrl($jamUrl, $jamName);
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
                $this->cleanupScreenshotOptimizedImages($game, $index);
            }
        }
    }

    private function cleanupScreenshotOptimizedImages(Game $game, int $screenshotIndex): void
    {
        try {
            $files = Storage::disk('public')->files('screenshots');
            $pattern = "/^{$game->id}_screenshot_{$screenshotIndex}_[a-f0-9]{8}/";
            foreach ($files as $file) {
                $filename = basename($file);
                if (preg_match($pattern, $filename)) {
                    Storage::disk('public')->delete($file);
                    Log::info('Cleaned up optimized screenshot', [
                        'game_id' => $game->id,
                        'screenshot_index' => $screenshotIndex,
                        'file' => $filename,
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to cleanup optimized screenshot images', [
                'game_id' => $game->id,
                'screenshot_index' => $screenshotIndex,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

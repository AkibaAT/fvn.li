<?php

declare(strict_types=1);

use App\Models\Game;
use App\Services\ItchGameMetadataExtractor;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('price extraction from HTML', function () {
    test('extracts price from HTML when no API price is set', function () {
        $game = Game::factory()->create([
            'is_paid' => false,
            'min_price' => 0,
            'is_on_sale' => false,
        ]);

        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <div class="buy_game_section">
        <div class="base_price">$14.99</div>
    </div>
</body>
</html>
HTML;

        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $extractor = new ItchGameMetadataExtractor();
        
        $extractor->extractPriceInformation($game, $doc, false);

        expect($game->min_price)->toBe(14.99)
            ->and($game->is_paid)->toBeTrue();
    });

    test('preserves API price when preserveApiPrice is true', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 14.99,
            'is_on_sale' => true,
            'sale_discount_percent' => 20,
        ]);

        // Set the flag that indicates price was set from API
        $game->priceSetFromApi = true;

        // HTML that would normally overwrite the price
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <div class="buy_game_section">
        <div class="base_price">$0.00</div>
    </div>
</body>
</html>
HTML;

        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $extractor = new ItchGameMetadataExtractor();
        
        $extractor->extractPriceInformation($game, $doc, true);

        // Price should be preserved from API
        expect($game->min_price)->toBe(14.99)
            ->and($game->is_paid)->toBeTrue()
            ->and($game->is_on_sale)->toBeTrue()
            ->and($game->sale_discount_percent)->toBe(20);
    });

    test('overwrites price when preserveApiPrice is false', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 14.99,
            'is_on_sale' => false,
        ]);

        // HTML with different price
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <div class="buy_game_section">
        <div class="base_price">$9.99</div>
    </div>
</body>
</html>
HTML;

        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $extractor = new ItchGameMetadataExtractor();
        
        $extractor->extractPriceInformation($game, $doc, false);

        // Price should be updated from HTML
        expect($game->min_price)->toBe(9.99);
    });

    test('detects sale tag from HTML', function () {
        $game = Game::factory()->create([
            'is_paid' => false,
            'min_price' => 0,
            'is_on_sale' => false,
        ]);

        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <div class="buy_game_section">
        <div class="sale_tag">On Sale!</div>
        <div class="base_price">$19.99</div>
    </div>
</body>
</html>
HTML;

        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $extractor = new ItchGameMetadataExtractor();
        
        $extractor->extractPriceInformation($game, $doc, false);

        expect($game->is_on_sale)->toBeTrue()
            ->and($game->min_price)->toBe(19.99);
    });

    test('handles missing buy section as free game', function () {
        $game = Game::factory()->create([
            'is_paid' => false,
            'min_price' => 0,
            'is_on_sale' => false,
        ]);

        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <div class="game_content">
        No buy section here
    </div>
</body>
</html>
HTML;

        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $extractor = new ItchGameMetadataExtractor();

        $extractor->extractPriceInformation($game, $doc, false);

        expect($game->min_price)->toBe(0.0)
            ->and($game->is_paid)->toBeFalse()
            ->and($game->is_on_sale)->toBeFalse();
    });

    test('preserves paid status when buy section missing but preserveApiPrice is true', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 14.99,
            'is_on_sale' => false,
        ]);

        $game->priceSetFromApi = true;

        // HTML without buy section
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <div class="game_content">
        No buy section here
    </div>
</body>
</html>
HTML;

        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $extractor = new ItchGameMetadataExtractor();
        
        $extractor->extractPriceInformation($game, $doc, true);

        // Price should be preserved even though buy section is missing
        expect($game->min_price)->toBe(14.99)
            ->and($game->is_paid)->toBeTrue();
    });

    test('handles missing price element in buy section', function () {
        $game = Game::factory()->create([
            'is_paid' => false,
            'min_price' => 0,
            'is_on_sale' => false,
        ]);

        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <div class="buy_game_section">
        <div class="some_other_element">Content</div>
    </div>
</body>
</html>
HTML;

        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $extractor = new ItchGameMetadataExtractor();

        $extractor->extractPriceInformation($game, $doc, false);

        expect($game->min_price)->toBe(0.0);
    });

    test('extracts price with various formats', function () {
        $testCases = [
            ['html' => '$14.99', 'expected' => 14.99],
            ['html' => '14.99', 'expected' => 14.99],
            ['html' => '$5', 'expected' => 5.0],
            ['html' => '9.95', 'expected' => 9.95],
        ];

        foreach ($testCases as $testCase) {
            $game = Game::factory()->create([
                'is_paid' => false,
                'min_price' => 0,
            ]);

            $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <div class="buy_game_section">
        <div class="base_price">{$testCase['html']}</div>
    </div>
</body>
</html>
HTML;

            $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);
            $extractor = new ItchGameMetadataExtractor();
            
            $extractor->extractPriceInformation($game, $doc, false);

            expect($game->min_price)->toBe($testCase['expected']);
        }
    });
});


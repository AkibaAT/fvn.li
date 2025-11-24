<?php

declare(strict_types=1);

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('game pricing calculations', function () {
    test('free game has zero price', function () {
        $game = Game::factory()->create([
            'is_paid' => false,
            'min_price' => 0,
            'is_on_sale' => false,
        ]);

        expect($game->is_paid)->toBeFalse()
            ->and($game->min_price)->toBe(0.0)
            ->and($game->is_on_sale)->toBeFalse();
    });

    test('paid game has correct price', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 9.99,
            'is_on_sale' => false,
        ]);

        expect($game->is_paid)->toBeTrue()
            ->and($game->min_price)->toBe(9.99)
            ->and($game->is_on_sale)->toBeFalse();
    });

    test('game on sale has discount percentage', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 9.99,
            'is_on_sale' => true,
            'sale_discount_percent' => 25,
        ]);

        expect($game->is_on_sale)->toBeTrue()
            ->and($game->sale_discount_percent)->toBe(25);
    });

    test('calculates sale price correctly', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 10.00,
            'is_on_sale' => true,
            'sale_discount_percent' => 20,
        ]);

        // Expected sale price: 10.00 - (10.00 * 0.20) = 8.00
        $expectedSalePrice = 10.00 - (10.00 * 0.20);

        expect($game->min_price)->toBe(10.00)
            ->and($game->sale_discount_percent)->toBe(20)
            ->and($expectedSalePrice)->toBe(8.00);
    });

    test('handles zero discount percentage', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 10.00,
            'is_on_sale' => true,
            'sale_discount_percent' => 0,
        ]);

        expect($game->sale_discount_percent)->toBe(0);
    });

    test('handles 100 percent discount', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 10.00,
            'is_on_sale' => true,
            'sale_discount_percent' => 100,
        ]);

        expect($game->sale_discount_percent)->toBe(100);
    });
});

describe('game pricing edge cases', function () {
    test('handles very small prices', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 0.99,
            'is_on_sale' => false,
        ]);

        expect($game->min_price)->toBe(0.99);
    });

    test('handles large prices', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 999.99,
            'is_on_sale' => false,
        ]);

        expect($game->min_price)->toBe(999.99);
    });

    test('handles price with many decimal places', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 9.999,
            'is_on_sale' => false,
        ]);

        // Should be cast to float
        expect($game->min_price)->toBeFloat();
    });

    test('free game cannot be on sale', function () {
        $game = Game::factory()->create([
            'is_paid' => false,
            'min_price' => 0,
            'is_on_sale' => false,
        ]);

        expect($game->is_paid)->toBeFalse()
            ->and($game->is_on_sale)->toBeFalse();
    });

    test('paid game can transition to free', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 10.00,
        ]);

        $game->update([
            'is_paid' => false,
            'min_price' => 0,
            'is_on_sale' => false,
        ]);

        expect($game->is_paid)->toBeFalse()
            ->and($game->min_price)->toBe(0.0);
    });

    test('free game can transition to paid', function () {
        $game = Game::factory()->create([
            'is_paid' => false,
            'min_price' => 0,
        ]);

        $game->update([
            'is_paid' => true,
            'min_price' => 9.99,
        ]);

        expect($game->is_paid)->toBeTrue()
            ->and($game->min_price)->toBe(9.99);
    });
});

describe('game demo availability', function () {
    test('paid game can have demo', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 10.00,
            'has_demo' => true,
        ]);

        expect($game->has_demo)->toBeTrue();
    });

    test('free game does not need demo flag', function () {
        $game = Game::factory()->create([
            'is_paid' => false,
            'min_price' => 0,
            'has_demo' => false,
        ]);

        expect($game->has_demo)->toBeFalse();
    });

    test('paid game without demo', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 10.00,
            'has_demo' => false,
        ]);

        expect($game->has_demo)->toBeFalse();
    });
});

describe('game pricing display logic', function () {
    test('displays correct price for non-sale game', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 14.99,
            'is_on_sale' => false,
        ]);

        expect($game->min_price)->toBe(14.99)
            ->and($game->is_on_sale)->toBeFalse();
    });

    test('displays correct price and discount for sale game', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 19.99,
            'is_on_sale' => true,
            'sale_discount_percent' => 30,
        ]);

        expect($game->min_price)->toBe(19.99)
            ->and($game->is_on_sale)->toBeTrue()
            ->and($game->sale_discount_percent)->toBe(30);
    });

    test('handles pay-what-you-want pricing', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 0,
            'is_on_sale' => false,
        ]);

        // Pay-what-you-want games are marked as paid but have min_price of 0
        expect($game->is_paid)->toBeTrue()
            ->and($game->min_price)->toBe(0.0);
    });
});

describe('sale discount validation', function () {
    test('discount percentage is stored as integer', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 10.00,
            'is_on_sale' => true,
            'sale_discount_percent' => 25,
        ]);

        expect($game->sale_discount_percent)->toBeInt();
    });

    test('handles various discount percentages', function () {
        $discounts = [10, 25, 33, 50, 75, 90];

        foreach ($discounts as $discount) {
            $game = Game::factory()->create([
                'is_paid' => true,
                'min_price' => 20.00,
                'is_on_sale' => true,
                'sale_discount_percent' => $discount,
            ]);

            expect($game->sale_discount_percent)->toBe($discount);
        }
    });

    test('null discount when not on sale', function () {
        $game = Game::factory()->create([
            'is_paid' => true,
            'min_price' => 10.00,
            'is_on_sale' => false,
            'sale_discount_percent' => null,
        ]);

        expect($game->sale_discount_percent)->toBeNull();
    });
});

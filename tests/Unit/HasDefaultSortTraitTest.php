<?php

declare(strict_types=1);

use App\Traits\HasDefaultSort;

// Create a test class that uses the trait
$testClass = new class
{
    use HasDefaultSort;
};

it('gets default sort field', function () use ($testClass) {
    expect($testClass::getDefaultSortField())->toBe('first_visible_at');
});

it('gets default sort direction', function () use ($testClass) {
    expect($testClass::getDefaultSortDirection())->toBe('desc');
});

it('checks if sort is default', function () use ($testClass) {
    expect($testClass::isDefaultSort('first_visible_at', 'desc'))->toBeTrue()
        ->and($testClass::isDefaultSort('first_visible_at', 'asc'))->toBeFalse()
        ->and($testClass::isDefaultSort('name', 'desc'))->toBeFalse()
        ->and($testClass::isDefaultSort('name', 'asc'))->toBeFalse();
});

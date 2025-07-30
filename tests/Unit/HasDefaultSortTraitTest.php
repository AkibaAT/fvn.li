<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Traits\HasDefaultSort;
use PHPUnit\Framework\TestCase;

class HasDefaultSortTraitTest extends TestCase
{
    use HasDefaultSort;

    public function test_get_default_sort_field(): void
    {
        $this->assertEquals('first_visible_at', self::getDefaultSortField());
    }

    public function test_get_default_sort_direction(): void
    {
        $this->assertEquals('desc', self::getDefaultSortDirection());
    }

    public function test_is_default_sort(): void
    {
        $this->assertTrue(self::isDefaultSort('first_visible_at', 'desc'));
        $this->assertFalse(self::isDefaultSort('first_visible_at', 'asc'));
        $this->assertFalse(self::isDefaultSort('name', 'desc'));
        $this->assertFalse(self::isDefaultSort('name', 'asc'));
    }
} 
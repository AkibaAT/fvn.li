<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait HasGamePricing
{
    /**
     * Get the current price (after discount if on sale)
     */
    public function getCurrentPriceAttribute(): ?float
    {
        if (! $this->is_paid || $this->min_price === null) {
            return null;
        }

        if ($this->is_on_sale && $this->sale_discount_percent) {
            return round($this->min_price * (1 - $this->sale_discount_percent / 100), 2);
        }

        return $this->min_price;
    }

    /**
     * Get the original price (before discount)
     */
    public function getOriginalPriceAttribute(): ?float
    {
        if (! $this->is_paid || $this->min_price === null) {
            return null;
        }

        // min_price always represents the original/base price
        return $this->min_price;
    }

    /**
     * Get the discount percentage (alias for sale_discount_percent)
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        return $this->sale_discount_percent;
    }
}

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

    /**
     * Format a price with the game's currency
     */
    public function formatPrice(?float $price): ?string
    {
        if ($price === null) {
            return null;
        }

        $currency = $this->currency ?? 'USD';

        // Common currency symbols
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'JPY' => '¥',
        ];

        $symbol = $symbols[$currency] ?? $currency . ' ';
        $decimals = $currency === 'JPY' ? 0 : 2;

        return $symbol . number_format($price, $decimals);
    }

    /**
     * Get the current price formatted with currency
     */
    public function getFormattedCurrentPriceAttribute(): ?string
    {
        return $this->formatPrice($this->current_price);
    }

    /**
     * Get the original price formatted with currency
     */
    public function getFormattedOriginalPriceAttribute(): ?string
    {
        return $this->formatPrice($this->original_price);
    }
}

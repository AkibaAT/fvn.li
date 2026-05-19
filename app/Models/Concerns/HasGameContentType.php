<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait HasGameContentType
{
    public function scopeVisualNovels($query)
    {
        return $query->where('content_type', 'visual_novel');
    }

    public function scopeAdjacentGames($query)
    {
        return $query->where('content_type', 'adjacent');
    }

    public function scopeOtherContent($query)
    {
        return $query->where('content_type', 'other');
    }

    public function scopePublicContent($query)
    {
        return $query->where('content_type', 'visual_novel');
    }

    public function scopeBotOnlyContent($query)
    {
        return $query->whereIn('content_type', ['adjacent', 'other']);
    }

    public function isVisualNovel(): bool
    {
        return $this->content_type === 'visual_novel';
    }

    public function isAdjacentGame(): bool
    {
        return $this->content_type === 'adjacent';
    }

    public function isOtherContent(): bool
    {
        return $this->content_type === 'other';
    }

    public function isPublicContent(): bool
    {
        return $this->content_type === 'visual_novel';
    }

    public function isBotOnlyContent(): bool
    {
        return in_array($this->content_type, ['adjacent', 'other'], true);
    }

    public function getContentTypeName(): string
    {
        return match ($this->content_type) {
            'visual_novel' => 'Visual Novel',
            'adjacent' => 'Adjacent Game',
            'other' => 'Other Content',
            default => 'Unknown',
        };
    }
}

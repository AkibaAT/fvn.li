<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\Game;
use Illuminate\View\Component;
use Illuminate\View\View;

class GameThumbnail extends Component
{
    public function __construct(
        public Game $game,
        public string $variant = 'medium',
        public ?string $class = null,
        public bool $lazy = false,
        public ?string $sizes = null
    ) {}

    public function render(): View
    {
        return view('games.components.thumbnail');
    }

    /**
     * Get default sizes attribute based on variant
     */
    public function getDefaultSizes(): string
    {
        return match ($this->variant) {
            'small' => '(max-width: 640px) 200px, 200px',
            'medium' => '(max-width: 768px) 200px, (max-width: 1024px) 400px, 400px',
            'large' => '(max-width: 768px) 400px, (max-width: 1280px) 600px, 800px',
            default => '100vw'
        };
    }
}

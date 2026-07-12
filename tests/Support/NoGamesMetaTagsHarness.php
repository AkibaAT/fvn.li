<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Traits\HasSocialMetaTags;

final class NoGamesMetaTagsHarness
{
    use HasSocialMetaTags;

    public function description(): string
    {
        return $this->getMetaDescription();
    }
}

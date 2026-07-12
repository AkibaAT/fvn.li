<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Traits\HasSocialMetaTags;

final class HeadingMetaTagsHarness
{
    use HasSocialMetaTags;

    public function getHeading(): string
    {
        return 'Games';
    }

    public function getAllTableRecordsCount(): int
    {
        return 1;
    }

    public function title(): string
    {
        return $this->getMetaTitle();
    }
}

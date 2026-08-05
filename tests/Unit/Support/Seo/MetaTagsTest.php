<?php

declare(strict_types=1);

use App\Support\Seo\MetaTags;

it('serializes the Blade metadata shape without unset values', function () {
    $metaTags = new MetaTags(
        title: 'A page',
        socialTitle: 'A social page',
        description: 'A description',
        noindex: false,
        tags: ['Visual Novels'],
        structuredData: ['@type' => 'WebPage'],
    );

    expect($metaTags->toArray())->toBe([
        'title' => 'A page',
        'socialTitle' => 'A social page',
        'description' => 'A description',
        'noindex' => false,
        'tags' => ['Visual Novels'],
        'structuredData' => ['@type' => 'WebPage'],
    ]);
});

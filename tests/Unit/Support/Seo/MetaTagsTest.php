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

it('serializes the complete metadata contract', function () {
    $metaTags = new MetaTags(
        title: 'Title',
        browserTitle: 'Browser title',
        socialTitle: 'Social title',
        description: 'Description',
        image: 'https://example.com/image.png',
        url: 'https://example.com/page',
        type: 'article',
        noindex: true,
        publishedTime: '2026-08-01T00:00:00+00:00',
        modifiedTime: '2026-08-02T00:00:00+00:00',
        author: 'Author',
        section: 'Visual Novels',
        tags: ['Furry'],
        structuredData: ['@type' => 'Article'],
        twitterCard: 'summary',
        siteName: 'FVN.li',
        locale: 'en_US',
    );

    expect($metaTags->toArray())->toBe([
        'title' => 'Title',
        'browserTitle' => 'Browser title',
        'socialTitle' => 'Social title',
        'description' => 'Description',
        'image' => 'https://example.com/image.png',
        'url' => 'https://example.com/page',
        'type' => 'article',
        'noindex' => true,
        'publishedTime' => '2026-08-01T00:00:00+00:00',
        'modifiedTime' => '2026-08-02T00:00:00+00:00',
        'author' => 'Author',
        'section' => 'Visual Novels',
        'tags' => ['Furry'],
        'structuredData' => ['@type' => 'Article'],
        'twitterCard' => 'summary',
        'siteName' => 'FVN.li',
        'locale' => 'en_US',
    ]);
});

<?php

declare(strict_types=1);

namespace App\Support\Seo;

final readonly class MetaTags
{
    public function __construct(
        public ?string $title = null,
        public ?string $browserTitle = null,
        public ?string $socialTitle = null,
        public ?string $description = null,
        public ?string $image = null,
        public ?string $url = null,
        public ?string $type = null,
        public ?bool $noindex = null,
        public ?string $publishedTime = null,
        public ?string $modifiedTime = null,
        public ?string $author = null,
        public ?string $section = null,
        public ?array $tags = null,
        public ?array $structuredData = null,
        public ?string $twitterCard = null,
        public ?string $siteName = null,
        public ?string $locale = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'browserTitle' => $this->browserTitle,
            'socialTitle' => $this->socialTitle,
            'description' => $this->description,
            'image' => $this->image,
            'url' => $this->url,
            'type' => $this->type,
            'noindex' => $this->noindex,
            'publishedTime' => $this->publishedTime,
            'modifiedTime' => $this->modifiedTime,
            'author' => $this->author,
            'section' => $this->section,
            'tags' => $this->tags,
            'structuredData' => $this->structuredData,
            'twitterCard' => $this->twitterCard,
            'siteName' => $this->siteName,
            'locale' => $this->locale,
        ], static fn (mixed $value): bool => $value !== null);
    }
}

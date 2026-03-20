<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlSanitizerService
{
    private HtmlSanitizer $reviewSanitizer;

    private HtmlSanitizer $descriptionSanitizer;

    private HtmlSanitizer $authorsSanitizer;

    public function __construct()
    {
        $this->reviewSanitizer = $this->createReviewSanitizer();
        $this->descriptionSanitizer = $this->createDescriptionSanitizer();
        $this->authorsSanitizer = $this->createAuthorsSanitizer();
    }

    public function sanitizeReview(?string $html): ?string
    {
        return $this->doSanitize($this->reviewSanitizer, $html);
    }

    public function sanitizeDescription(?string $html): ?string
    {
        return $this->doSanitize($this->descriptionSanitizer, $html);
    }

    public function sanitizeAuthors(?string $html): ?string
    {
        return $this->doSanitize($this->authorsSanitizer, $html);
    }

    public function sanitizeCss(?string $css): ?string
    {
        if ($css === null || $css === '') {
            return $css;
        }

        $css = preg_replace('#expression\s*\(#i', '(', $css);
        $css = preg_replace('#javascript\s*:#i', '', $css);
        $css = preg_replace('#vbscript\s*:#i', '', $css);
        $css = preg_replace('#-moz-binding#i', '', $css);
        $css = preg_replace('#@import#i', '', $css);
        $css = preg_replace('#behavior\s*:#i', '', $css);
        $css = preg_replace('/url\s*\(\s*["\']?\s*javascript\s*:/i', 'url(', $css);

        return $css;
    }

    public function sanitizeGameModel(Game $game): void
    {
        $game->authors = $this->sanitizeAuthors($game->authors);
        $game->description = $this->sanitizeDescription($game->description);
        $game->full_description = $this->sanitizeDescription($game->full_description);
        $game->custom_description = $this->sanitizeDescription($game->custom_description);
        $game->custom_css = $this->sanitizeCss($game->custom_css);
    }

    private function doSanitize(HtmlSanitizer $sanitizer, ?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return trim(preg_replace('/\s+/', ' ', str_replace("\u{00A0}", ' ', $sanitizer->sanitize($html))));
    }

    private function createReviewSanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowAttribute('class', ['*'])
            ->allowAttribute('style', ['span', 'p', 'div', 'strong', 'em', 'b', 'i'])
            ->allowAttribute('href', ['a'])
            ->allowAttribute('target', ['a'])
            ->allowAttribute('rel', ['a'])
            ->allowAttribute('src', ['img'])
            ->allowAttribute('alt', ['img'])
            ->allowAttribute('width', ['img'])
            ->allowAttribute('height', ['img'])
            ->allowAttribute('colspan', ['td', 'th'])
            ->allowAttribute('rowspan', ['td', 'th'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        return new HtmlSanitizer($config);
    }

    private function createDescriptionSanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowAttribute('class', ['*'])
            ->allowAttribute('id', ['*'])
            ->allowAttribute('style', ['span', 'p', 'div', 'strong', 'em', 'b', 'i', 'img'])
            ->allowAttribute('href', ['a'])
            ->allowAttribute('target', ['a'])
            ->allowAttribute('rel', ['a'])
            ->allowAttribute('src', ['img'])
            ->allowAttribute('alt', ['img'])
            ->allowAttribute('width', ['img', 'iframe'])
            ->allowAttribute('height', ['img', 'iframe'])
            ->allowAttribute('colspan', ['td', 'th'])
            ->allowAttribute('rowspan', ['td', 'th'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        return new HtmlSanitizer($config);
    }

    private function createAuthorsSanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowAttribute('class', ['*'])
            ->allowAttribute('style', ['span', 'a'])
            ->allowAttribute('href', ['a'])
            ->allowAttribute('target', ['a'])
            ->allowAttribute('rel', ['a'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer');

        return new HtmlSanitizer($config);
    }
}

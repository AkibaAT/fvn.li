<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlSanitizerService
{
    private const ALLOWED_INLINE_STYLE_PROPERTIES = [
        'background-color',
        'color',
        'font-family',
        'font-size',
        'font-style',
        'font-weight',
        'height',
        'line-height',
        'list-style-type',
        'max-height',
        'max-width',
        'text-align',
        'text-decoration',
        'text-decoration-line',
        'vertical-align',
        'white-space',
        'width',
    ];

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

    public function sanitizeFvnReview(?string $html): ?string
    {
        return $this->doSanitize($this->reviewSanitizer, $html, preserveLineBreaks: true);
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
        // Only '<' can open a `</style>` breakout; '>' must stay literal so
        // child combinators (`.a > .b`) keep working.
        $css = str_replace('<', '\\3C ', $css);

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

    private function doSanitize(HtmlSanitizer $sanitizer, ?string $html, bool $preserveLineBreaks = false): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $html = $this->sanitizeInlineStyleAttributes($sanitizer->sanitize($html));
        $html = preg_replace($preserveLineBreaks ? '/[^\S\r\n]+/' : '/\s+/', ' ', str_replace("\u{00A0}", ' ', $html));

        return trim($preserveLineBreaks ? preg_replace('/(?:\R[^\S\r\n]*){3,}/u', "\n\n", $html) : $html);
    }

    private function sanitizeInlineStyleAttributes(string $html): string
    {
        if (! str_contains(strtolower($html), 'style=')) {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="fvn-sanitizer-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $html;
        }

        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//*[@style]') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $style = $this->sanitizeInlineCss($element->getAttribute('style'));
            if ($style === '') {
                $element->removeAttribute('style');

                continue;
            }

            $element->setAttribute('style', $style);
        }

        $root = $dom->getElementById('fvn-sanitizer-root');
        if (! $root) {
            return $html;
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }

    private function sanitizeInlineCss(string $css): string
    {
        $css = $this->sanitizeCss($css) ?? '';
        $declarations = [];

        foreach (explode(';', $css) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            $value = trim($value);

            if (
                $property === ''
                || $value === ''
                || ! in_array($property, self::ALLOWED_INLINE_STYLE_PROPERTIES, true)
                || preg_match('/url\s*\(|expression\s*\(|javascript\s*:|vbscript\s*:|data\s*:|@import|-moz-binding|behavior\s*:/i', $value)
            ) {
                continue;
            }

            $declarations[] = "{$property}:{$value}";
        }

        return implode(';', $declarations);
    }

    private function createReviewSanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowAttribute('class', '*')
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
            ->forceAttribute('a', 'rel', 'noopener');

        return new HtmlSanitizer($config);
    }

    private function createDescriptionSanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowAttribute('class', '*')
            ->allowAttribute('id', '*')
            ->allowAttribute('style', ['span', 'p', 'div', 'strong', 'em', 'b', 'i', 'img'])
            ->allowAttribute('href', ['a'])
            ->allowAttribute('target', ['a'])
            ->allowAttribute('rel', ['a'])
            ->allowAttribute('src', ['img', 'video', 'source'])
            ->allowAttribute('autoplay', ['video'])
            ->allowAttribute('alt', ['img'])
            ->allowAttribute('width', ['img', 'iframe'])
            ->allowAttribute('height', ['img', 'iframe'])
            ->allowAttribute('colspan', ['td', 'th'])
            ->allowAttribute('rowspan', ['td', 'th'])
            ->forceAttribute('video', 'muted', '')
            ->forceAttribute('a', 'rel', 'noopener');

        return new HtmlSanitizer($config);
    }

    private function createAuthorsSanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowAttribute('class', '*')
            ->allowAttribute('style', ['span', 'a'])
            ->allowAttribute('href', ['a'])
            ->allowAttribute('target', ['a'])
            ->allowAttribute('rel', ['a'])
            ->forceAttribute('a', 'rel', 'noopener');

        return new HtmlSanitizer($config);
    }
}

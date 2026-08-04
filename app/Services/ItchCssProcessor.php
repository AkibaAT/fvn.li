<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Sabberworm\CSS\CSSList\CSSList;
use Sabberworm\CSS\CSSList\KeyFrame;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser as CssParser;
use Sabberworm\CSS\Property\AtRule;
use Sabberworm\CSS\Property\Charset;
use Sabberworm\CSS\Property\CSSNamespace;
use Sabberworm\CSS\Property\Import;
use Sabberworm\CSS\Property\Selector;
use Sabberworm\CSS\Rule\Rule;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\RuleSet\RuleSet;

class ItchCssProcessor
{
    private const SCOPE_SELECTOR = '.game_description';

    private const array HEADER_SELECTORS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    private const array ITCH_PAGE_SHELL_SELECTORS = [
        '#wrapper',
        '.wrapper',
        '.inner_column',
        '.game_frame',
        '.game_loading',
        '.view_game_page',
        '.game_devlog_page',
        '.game_devlog_post_page',
        '.game_comments_widget',
        '.game_community_preview_widget',
    ];

    private const array DISALLOWED_PROPERTIES = [
        'all',
        'animation',
        'animation-delay',
        'animation-direction',
        'animation-duration',
        'animation-fill-mode',
        'animation-iteration-count',
        'animation-name',
        'animation-play-state',
        'animation-timing-function',
        'backdrop-filter',
        'behavior',
        'bottom',
        'content',
        'cursor',
        'inset',
        'inset-block',
        'inset-block-end',
        'inset-block-start',
        'inset-inline',
        'inset-inline-end',
        'inset-inline-start',
        'left',
        'mask',
        'mask-border',
        'mask-border-source',
        'mask-image',
        'pointer-events',
        'right',
        'top',
        'visibility',
        'z-index',
    ];

    public function process(?string $css): ?string
    {
        if (empty($css)) {
            return null;
        }

        try {
            $parser = new CssParser($css);
            $document = $parser->parse();

            $this->filterRules($document);
            $this->scopeSelectors($document);

            $output = $document->render(OutputFormat::createCompact());
            $output = $this->escapeHtmlDelimiters($output);

            return ! empty($output) ? $output : null;
        } catch (Exception $e) {
            Log::error('CSS Parsing/Processing failed: ' . $e->getMessage(), ['css' => $css]);

            return null; // Return null or original CSS on error? Returning null for now.
        }
    }

    private function escapeHtmlDelimiters(string $css): string
    {
        // Only '<' can open a `</style>` breakout; '>' must stay literal so
        // child combinators (`.a > .b`) keep working.
        return str_replace('<', '\\3C ', $css);
    }

    private function filterRules(CSSList $list): void
    {
        $rulesToRemove = [];
        foreach ($list->getContents() as $i => $rule) {
            if ($rule instanceof AtRule && $rule instanceof CSSList) {
                if (! $this->isSafeMediaRule($rule)) {
                    $rulesToRemove[] = $i;

                    continue;
                }

                $this->filterRules($rule);
                if (empty($rule->getContents())) {
                    $rulesToRemove[] = $i;
                }

                continue;
            }

            if (
                $rule instanceof Import
                || $rule instanceof Charset
                || $rule instanceof CSSNamespace
                || $rule instanceof AtRule
                || $rule instanceof KeyFrame
            ) {
                $rulesToRemove[] = $i;

                continue;
            }

            if ($rule instanceof DeclarationBlock) { // Includes RuleSet and AtRuleSet
                if ($this->targetsHeaders($rule)) {
                    $rulesToRemove[] = $i;

                    continue;
                }

                $this->filterUnsafeProperties($rule);

                // If the ruleset is now empty, mark it for removal
                if ($rule instanceof RuleSet && empty($rule->getRules())) {
                    $rulesToRemove[] = $i;

                    continue;
                }
            } elseif ($rule instanceof CSSList) { // Recurse into nested lists (like @media)
                $this->filterRules($rule);
                // If the nested list is now empty, mark it for removal
                if (empty($rule->getContents())) {
                    $rulesToRemove[] = $i;
                }
            }
        }

        foreach (array_reverse($rulesToRemove) as $index) {
            $list->remove($list->getContents()[$index]);
        }
    }

    private function isSafeMediaRule(AtRule $rule): bool
    {
        if (strtolower($rule->atRuleName()) !== 'media' || ! method_exists($rule, 'atRuleArgs')) {
            return false;
        }

        $arguments = trim($rule->atRuleArgs());

        return $arguments !== ''
            && strlen($arguments) <= 500
            && preg_match('/(?:url\s*\(|https?:|data:|\/\/)/i', $arguments) !== 1
            && preg_match('/^[a-z0-9\s():.,\/%+\-]+$/i', $arguments) === 1;
    }

    private function scopeSelectors(CSSList $list): void
    {
        $rulesToRemove = [];

        foreach ($list->getContents() as $i => $rule) {
            if ($rule instanceof DeclarationBlock) {
                $scopedSelectors = [];

                foreach ($rule->getSelectors() as $selector) {
                    if (! $selector instanceof Selector) {
                        continue;
                    }

                    $scopedSelector = $this->scopeSelector($selector->getSelector());
                    if ($scopedSelector !== null) {
                        $scopedSelectors[] = $scopedSelector;
                    }
                }

                if (empty($scopedSelectors)) {
                    $rulesToRemove[] = $i;

                    continue;
                }

                $rule->setSelectors($scopedSelectors, $list);
            } elseif ($rule instanceof CSSList) {
                $this->scopeSelectors($rule);

                if (empty($rule->getContents())) {
                    $rulesToRemove[] = $i;
                }
            }
        }

        foreach (array_reverse($rulesToRemove) as $index) {
            $list->remove($list->getContents()[$index]);
        }
    }

    private function scopeSelector(string $selector): ?string
    {
        $selector = trim($selector);
        if ($selector === '') {
            return null;
        }

        if (preg_match('/(^|[\s>+~,(])(?:html|body|:root)(?=$|[\s>+~.#[(:])/i', $selector)) {
            return null;
        }

        if ($this->targetsItchPageShell($selector)) {
            return null;
        }

        if (preg_match('/^' . preg_quote(self::SCOPE_SELECTOR, '/') . '(?=$|[.#[(:>+~])/i', $selector)) {
            return null;
        }

        if (
            str_starts_with($selector, self::SCOPE_SELECTOR)
            || preg_match('/^' . preg_quote(self::SCOPE_SELECTOR, '/') . '(?=$|[\s>+~.#[(:])/i', $selector)
        ) {
            return $selector;
        }

        return self::SCOPE_SELECTOR . ' ' . $selector;
    }

    private function targetsItchPageShell(string $selector): bool
    {
        $selector = preg_replace(
            '/^' . preg_quote(self::SCOPE_SELECTOR, '/') . '\s+/i',
            '',
            trim($selector)
        ) ?? $selector;

        foreach (self::ITCH_PAGE_SHELL_SELECTORS as $shellSelector) {
            if (preg_match('/^' . preg_quote($shellSelector, '/') . '(?=$|[\s>+~.#[(:])/i', $selector)) {
                return true;
            }
        }

        return false;
    }

    private function targetsHeaders(DeclarationBlock $ruleSet): bool
    {
        $selectors = $ruleSet->getSelectors();
        foreach ($selectors as $selector) {
            if (! $selector instanceof Selector) {
                continue;
            }

            $selectorText = $selector->getSelector();

            foreach (self::HEADER_SELECTORS as $header) {
                if (preg_match('/(^|,)\s*' . preg_quote($header, '/') . '(\s|\.|\#|\:|\[|,|$)/i', $selectorText)) {
                    return true;
                }

                if (preg_match('/\b' . preg_quote($header, '/') . '(\.|\#|\:|\[)/i', $selectorText)) {
                    return true;
                }

                if (preg_match('/[\s,>+~]' . preg_quote($header, '/') . '(\s|\.|\#|\:|\[|,|$)/i', $selectorText)) {
                    return true;
                }

                if (preg_match('/(^|,)\s*' . preg_quote($header, '/') . '\s*[>+~]/i', $selectorText)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function filterUnsafeProperties(DeclarationBlock $block): void
    {
        $declarationsToRemove = [];
        foreach ($block->getRules() as $declaration) {
            if ($declaration instanceof Rule) {
                $propertyName = strtolower($declaration->getRule());

                if (in_array($propertyName, self::DISALLOWED_PROPERTIES, true)) {
                    $declarationsToRemove[] = $declaration;

                    continue;
                }

                $value = $declaration->getValue();
                $valueString = is_object($value) && method_exists($value, 'render')
                    ? $value->render(OutputFormat::createCompact())
                    : (string) $value;

                if ($propertyName === 'position' && ! in_array(strtolower(trim($valueString)), ['relative', 'static'], true)) {
                    $declarationsToRemove[] = $declaration;

                    continue;
                }

                if (preg_match('/(?:url\s*\(|(?:java|vb)script\s*:|data\s*:|https?:\/\/|\/\/|@import|-moz-binding|expression\s*\(|behavior\s*:)/i',
                    $valueString)) {
                    $declarationsToRemove[] = $declaration;
                }
            }
        }

        foreach ($declarationsToRemove as $declaration) {
            $block->removeRule($declaration);
        }
    }
}

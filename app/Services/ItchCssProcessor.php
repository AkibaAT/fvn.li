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
use Sabberworm\CSS\Value\Color;
use Sabberworm\CSS\Value\CSSFunction;
use Sabberworm\CSS\Value\CSSString;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\Size;

class ItchCssProcessor
{
    private const SCOPE_SELECTOR = '.game_description';

    private const array HEADER_SELECTORS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

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
        'clip-path',
        'content',
        'cursor',
        'display',
        'filter',
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
        'opacity',
        'pointer-events',
        'position',
        'right',
        'top',
        'transform',
        'transition',
        'transition-delay',
        'transition-duration',
        'transition-property',
        'transition-timing-function',
        'visibility',
        'z-index',
    ];

    private const array COLOR_PROPERTIES = [
        // Basic color properties
        'color',
        'background',
        'background-color',
        'background-image',
        'border',
        'border-top',
        'border-right',
        'border-bottom',
        'border-left',
        'border-color',
        'border-top-color',
        'border-right-color',
        'border-bottom-color',
        'border-left-color',
        'outline',
        'outline-color',
        'text-shadow',
        'box-shadow',
        'fill', // For SVGs
        'stroke', // For SVGs
        'stroke-color',

        // Additional color-related properties
        'caret-color',
        'column-rule-color',
        'text-decoration-color',
        'accent-color',
        'scrollbar-color',
        'box-shadow',
        'text-shadow',
        'filter', // Can contain color functions
        'backdrop-filter', // Can contain color functions
        'border-image',
        'border-image-source',
        'list-style-image',
        'mask-image',
        'mask-border',
        'mask-border-source',
        'gradient',
        'linear-gradient',
        'radial-gradient',
        'conic-gradient',
        'repeating-linear-gradient',
        'repeating-radial-gradient',
        'repeating-conic-gradient',

        // Additional border properties that might contain colors
        'border-image-outset',
        'border-image-repeat',
        'border-image-slice',
        'border-image-width',
        'border-radius',
        'border-style',
        'border-width',
        'border-top-style',
        'border-right-style',
        'border-bottom-style',
        'border-left-style',
        'border-top-width',
        'border-right-width',
        'border-bottom-width',
        'border-left-width',

        // Text decoration properties
        'text-decoration',
        'text-decoration-line',
        'text-decoration-style',
        'text-emphasis',
        'text-emphasis-color',
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
            // Log the error or handle it appropriately
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
                // Check if selector targets headers
                if ($this->targetsHeaders($rule)) {
                    $rulesToRemove[] = $i;

                    continue;
                }

                // Filter color properties within the block
                $this->filterColorProperties($rule);

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

        // Remove marked rules in reverse order to avoid index issues
        foreach (array_reverse($rulesToRemove) as $index) {
            $list->remove($list->getContents()[$index]);
        }
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

        if (
            str_starts_with($selector, self::SCOPE_SELECTOR)
            || preg_match('/^' . preg_quote(self::SCOPE_SELECTOR, '/') . '(?=$|[\s>+~.#[(:])/i', $selector)
        ) {
            return $selector;
        }

        return self::SCOPE_SELECTOR . ' ' . $selector;
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
                // Check for direct header tag (e.g., 'h1', 'h2')
                if (preg_match('/(^|,)\s*' . preg_quote($header, '/') . '(\s|\.|\#|\:|\[|,|$)/i', $selectorText)) {
                    return true;
                }

                // Check for header tag with class/id/attribute (e.g., 'h1.class', 'h2#id')
                if (preg_match('/\b' . preg_quote($header, '/') . '(\.|\#|\:|\[)/i', $selectorText)) {
                    return true;
                }

                // Check for header tag as part of a descendant selector (e.g., 'div h1', '.class h2')
                if (preg_match('/[\s,>+~]' . preg_quote($header, '/') . '(\s|\.|\#|\:|\[|,|$)/i', $selectorText)) {
                    return true;
                }

                // Check for header tag as part of a complex selector (e.g., 'h1 > span', 'h2 + p')
                if (preg_match('/(^|,)\s*' . preg_quote($header, '/') . '\s*[>+~]/i', $selectorText)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function filterColorProperties(DeclarationBlock $block): void
    {
        $declarationsToRemove = [];
        foreach ($block->getRules() as $declaration) {
            if ($declaration instanceof Rule) {
                $propertyName = strtolower($declaration->getRule());

                if (in_array($propertyName, self::DISALLOWED_PROPERTIES, true)) {
                    $declarationsToRemove[] = $declaration;

                    continue;
                }

                // Check if the property is a color property OR if it's a shorthand potentially containing color
                if (in_array($propertyName, self::COLOR_PROPERTIES)) {
                    $declarationsToRemove[] = $declaration;

                    continue;
                }

                // Check values for color functions or color names in any property
                $value = $declaration->getValue();

                // Handle special value objects that can't be cast to string
                if ($value instanceof Color) {
                    // Color objects are colors by definition, so remove them
                    $declarationsToRemove[] = $declaration;

                    continue;
                }

                if ($value instanceof RuleValueList) {
                    // Convert RuleValueList to string using render method with OutputFormat
                    $valueString = $value->render(OutputFormat::createCompact());
                } elseif ($value instanceof Size) {
                    // Convert Size to string using render method with OutputFormat
                    $valueString = $value->render(OutputFormat::createCompact());
                } elseif ($value instanceof CSSString) {
                    // Convert CSSString to string using render method with OutputFormat
                    $valueString = $value->render(OutputFormat::createCompact());
                } elseif ($value instanceof CSSFunction) {
                    // Convert CSSFunction to string using render method with OutputFormat
                    $valueString = $value->render(OutputFormat::createCompact());
                } else {
                    $valueString = (string) $value;
                }

                if (preg_match('/(?:url\s*\(|(?:java|vb)script\s*:|data\s*:|https?:\/\/|\/\/|@import|-moz-binding|expression\s*\(|behavior\s*:)/i',
                    $valueString)) {
                    $declarationsToRemove[] = $declaration;

                    continue;
                }

                // Check for hex colors, rgb/rgba/hsl/hsla functions
                if (preg_match('/(#[0-9a-fA-F]{3,8}\b|\b(rgb|rgba|hsl|hsla|hwb|lab|lch|color)\s*\()/i', $valueString)) {
                    $declarationsToRemove[] = $declaration;

                    continue;
                }

                // Check for named colors
                if ($this->containsNamedColor($valueString)) {
                    $declarationsToRemove[] = $declaration;

                    continue;
                }

                // Check for gradient functions
                if (preg_match('/\b(linear-gradient|radial-gradient|conic-gradient|repeating-linear-gradient|repeating-radial-gradient|repeating-conic-gradient)\s*\(/i',
                    $valueString)) {
                    $declarationsToRemove[] = $declaration;

                    continue;
                }

                // Check for properties that might contain colors in shorthand notation
                if (in_array($propertyName, ['font', 'animation', 'transition']) &&
                    $this->mightContainColor($valueString)) {
                    $declarationsToRemove[] = $declaration;
                }
            }
        }

        // Remove marked declarations
        foreach ($declarationsToRemove as $declaration) {
            $block->removeRule($declaration);
        }
    }

    /**
     * Check if a string contains a named CSS color
     */
    private function containsNamedColor(string $value): bool
    {
        // List of CSS color names
        static $colorNames = [
            'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black', 'blanchedalmond',
            'blue', 'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse', 'chocolate', 'coral',
            'cornflowerblue',
            'cornsilk', 'crimson', 'cyan', 'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgrey',
            'darkkhaki', 'darkmagenta', 'darkolivegreen', 'darkorange', 'darkorchid', 'darkred', 'darksalmon',
            'darkseagreen', 'darkslateblue', 'darkslategray', 'darkslategrey', 'darkturquoise', 'darkviolet',
            'deeppink',
            'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen', 'fuchsia',
            'gainsboro', 'ghostwhite', 'gold', 'goldenrod', 'gray', 'green', 'greenyellow', 'grey', 'honeydew',
            'hotpink',
            'indianred', 'indigo', 'ivory', 'khaki', 'lavender', 'lavenderblush', 'lawngreen', 'lemonchiffon',
            'lightblue',
            'lightcoral', 'lightcyan', 'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey', 'lightpink',
            'lightsalmon', 'lightseagreen', 'lightskyblue', 'lightslategray', 'lightslategrey', 'lightsteelblue',
            'lightyellow', 'lime', 'limegreen', 'linen', 'magenta', 'maroon', 'mediumaquamarine', 'mediumblue',
            'mediumorchid', 'mediumpurple', 'mediumseagreen', 'mediumslateblue', 'mediumspringgreen', 'mediumturquoise',
            'mediumvioletred', 'midnightblue', 'mintcream', 'mistyrose', 'moccasin', 'navajowhite', 'navy', 'oldlace',
            'olive', 'olivedrab', 'orange', 'orangered', 'orchid', 'palegoldenrod', 'palegreen', 'paleturquoise',
            'palevioletred', 'papayawhip', 'peachpuff', 'peru', 'pink', 'plum', 'powderblue', 'purple', 'rebeccapurple',
            'red', 'rosybrown', 'royalblue', 'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell', 'sienna',
            'silver', 'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow', 'springgreen', 'steelblue', 'tan',
            'teal',
            'thistle', 'tomato', 'transparent', 'turquoise', 'violet', 'wheat', 'white', 'whitesmoke', 'yellow',
            'yellowgreen',
            // CSS4 color keywords
            'currentcolor', 'inherit',
        ];

        // Check for word boundaries to avoid matching substrings (e.g., 'red' in 'bored')
        foreach ($colorNames as $color) {
            if (preg_match('/\b' . preg_quote($color, '/') . '\b/i', $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value might contain a color in a complex property
     */
    private function mightContainColor(string $value): bool
    {
        // Check for hex colors
        if (preg_match('/#[0-9a-fA-F]{3,8}\b/i', $value)) {
            return true;
        }

        // Check for color functions
        if (preg_match('/\b(rgb|rgba|hsl|hsla|hwb|lab|lch|color)\s*\(/i', $value)) {
            return true;
        }

        // Check for gradient functions
        if (preg_match('/\b(linear-gradient|radial-gradient|conic-gradient|repeating-linear-gradient|repeating-radial-gradient|repeating-conic-gradient)\s*\(/i',
            $value)) {
            return true;
        }

        // Check for named colors
        return $this->containsNamedColor($value);
    }
}

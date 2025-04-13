<?php

declare(strict_types=1);

namespace App\Services;

use Dom\HTMLDocument;
use Exception;
use Illuminate\Support\Facades\Log;

class ItchHtmlProcessor
{
    /**
     * Process HTML content from itch.io game pages
     * - Convert h1 tags to h2 and shift all other headers down one level
     * - Apply appropriate Tailwind CSS classes to headers
     * - Apply styling classes to other standard elements
     */
    public function process(?string $html): ?string
    {
        if (empty($html)) {
            return null;
        }

        try {
            // Create a DOM document from the HTML
            $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR);

            // Process headers
            $this->processHeaders($doc);

            // Process other elements
            $this->processLists($doc);
            $this->processParagraphs($doc);
            $this->processTables($doc);
            $this->processLinks($doc);
            $this->processImages($doc);

            // Get the body content only
            $body = $doc->querySelector('body');
            if ($body) {
                return trim($body->innerHTML);
            }

            // If no body tag (fragment), get the root content
            return trim($doc->innerHTML);
        } catch (Exception $e) {
            // Log the error
            Log::error('HTML Processing failed: ' . $e->getMessage(), ['html' => $html]);

            return $html; // Return original HTML on error
        }
    }

    /**
     * Process headers - convert h1 to h2 and shift all other headers down one level
     */
    private function processHeaders(HTMLDocument $doc): void
    {
        // Process headers in reverse order (h6 to h1) to avoid issues with changing DOM structure
        for ($level = 6; $level >= 1; $level--) {
            $headers = $doc->querySelectorAll("h{$level}");

            foreach ($headers as $header) {
                // Calculate new header level (h1 -> h2, h2 -> h3, etc.)
                $newLevel = min($level + 1, 6);

                // Create new header element
                $newHeader = $doc->createElement("h{$newLevel}");

                // Copy content and attributes
                $newHeader->innerHTML = $header->innerHTML;
                foreach ($header->attributes as $attribute) {
                    $newHeader->setAttribute($attribute->name, $attribute->value);
                }

                // Add appropriate Tailwind classes based on level
                $this->addHeaderClasses($newHeader, $newLevel);

                // Replace old header with new one
                $header->parentNode->replaceChild($newHeader, $header);
            }
        }
    }

    /**
     * Add appropriate Tailwind classes to headers based on level
     */
    private function addHeaderClasses($header, int $level): void
    {
        $classes = $header->getAttribute('class');
        $classArray = ! empty($classes) ? explode(' ', $classes) : [];

        // Base classes for all headers
        $baseClasses = ['font-semibold', 'text-gray-900', 'dark:text-gray-100', 'mb-4'];

        // Level-specific classes
        $levelClasses = [
            2 => ['text-2xl', 'mt-6'],
            3 => ['text-xl', 'mt-5'],
            4 => ['text-lg', 'mt-4'],
            5 => ['text-base', 'mt-3'],
            6 => ['text-sm', 'mt-2'],
        ];

        // Add base classes
        foreach ($baseClasses as $class) {
            if (! in_array($class, $classArray)) {
                $classArray[] = $class;
            }
        }

        // Add level-specific classes
        if (isset($levelClasses[$level])) {
            foreach ($levelClasses[$level] as $class) {
                if (! in_array($class, $classArray)) {
                    $classArray[] = $class;
                }
            }
        }

        // Set the updated class attribute
        $header->setAttribute('class', implode(' ', $classArray));
    }

    /**
     * Process paragraphs
     */
    private function processParagraphs(HTMLDocument $doc): void
    {
        $paragraphs = $doc->querySelectorAll('p');

        foreach ($paragraphs as $paragraph) {
            $classes = $paragraph->getAttribute('class');
            $classArray = ! empty($classes) ? explode(' ', $classes) : [];

            // Add Tailwind classes for paragraphs
            $paragraphClasses = ['text-gray-600', 'dark:text-gray-300', 'mb-4'];

            foreach ($paragraphClasses as $class) {
                if (! in_array($class, $classArray)) {
                    $classArray[] = $class;
                }
            }

            $paragraph->setAttribute('class', implode(' ', $classArray));
        }
    }

    /**
     * Process lists (ul, ol)
     */
    private function processLists(HTMLDocument $doc): void
    {
        // Process unordered lists
        $ulists = $doc->querySelectorAll('ul');
        foreach ($ulists as $list) {
            $classes = $list->getAttribute('class');
            $classArray = ! empty($classes) ? explode(' ', $classes) : [];

            // Add Tailwind classes for unordered lists
            $listClasses = ['list-disc', 'pl-5', 'mb-4', 'text-gray-600', 'dark:text-gray-300'];

            foreach ($listClasses as $class) {
                if (! in_array($class, $classArray)) {
                    $classArray[] = $class;
                }
            }

            $list->setAttribute('class', implode(' ', $classArray));
        }

        // Process ordered lists
        $olists = $doc->querySelectorAll('ol');
        foreach ($olists as $list) {
            $classes = $list->getAttribute('class');
            $classArray = ! empty($classes) ? explode(' ', $classes) : [];

            // Add Tailwind classes for ordered lists
            $listClasses = ['list-decimal', 'pl-5', 'mb-4', 'text-gray-600', 'dark:text-gray-300'];

            foreach ($listClasses as $class) {
                if (! in_array($class, $classArray)) {
                    $classArray[] = $class;
                }
            }

            $list->setAttribute('class', implode(' ', $classArray));
        }

        // Process list items
        $items = $doc->querySelectorAll('li');
        foreach ($items as $item) {
            $classes = $item->getAttribute('class');
            $classArray = ! empty($classes) ? explode(' ', $classes) : [];

            // Add Tailwind classes for list items
            $itemClasses = ['mb-1'];

            foreach ($itemClasses as $class) {
                if (! in_array($class, $classArray)) {
                    $classArray[] = $class;
                }
            }

            $item->setAttribute('class', implode(' ', $classArray));
        }
    }

    /**
     * Process tables
     */
    private function processTables(HTMLDocument $doc): void
    {
        $tables = $doc->querySelectorAll('table');

        foreach ($tables as $table) {
            $classes = $table->getAttribute('class');
            $classArray = ! empty($classes) ? explode(' ', $classes) : [];

            // Add Tailwind classes for tables
            $tableClasses = ['min-w-full', 'divide-y', 'divide-gray-200', 'dark:divide-gray-700', 'mb-4'];

            foreach ($tableClasses as $class) {
                if (! in_array($class, $classArray)) {
                    $classArray[] = $class;
                }
            }

            $table->setAttribute('class', implode(' ', $classArray));

            // Process table headers
            $headers = $table->querySelectorAll('th');
            foreach ($headers as $header) {
                $headerClasses = $header->getAttribute('class');
                $headerClassArray = ! empty($headerClasses) ? explode(' ', $headerClasses) : [];

                $thClasses = ['px-4', 'py-3', 'text-left', 'text-sm', 'font-semibold', 'text-gray-900', 'dark:text-gray-100'];

                foreach ($thClasses as $class) {
                    if (! in_array($class, $headerClassArray)) {
                        $headerClassArray[] = $class;
                    }
                }

                $header->setAttribute('class', implode(' ', $headerClassArray));
            }

            // Process table cells
            $cells = $table->querySelectorAll('td');
            foreach ($cells as $cell) {
                $cellClasses = $cell->getAttribute('class');
                $cellClassArray = ! empty($cellClasses) ? explode(' ', $cellClasses) : [];

                $tdClasses = ['px-4', 'py-3', 'text-sm', 'text-gray-600', 'dark:text-gray-300'];

                foreach ($tdClasses as $class) {
                    if (! in_array($class, $cellClassArray)) {
                        $cellClassArray[] = $class;
                    }
                }

                $cell->setAttribute('class', implode(' ', $cellClassArray));
            }
        }
    }

    /**
     * Process links
     */
    private function processLinks(HTMLDocument $doc): void
    {
        $links = $doc->querySelectorAll('a');

        foreach ($links as $link) {
            $classes = $link->getAttribute('class');
            $classArray = ! empty($classes) ? explode(' ', $classes) : [];

            // Add Tailwind classes for links
            $linkClasses = ['text-blue-600', 'dark:text-blue-400', 'hover:underline'];

            foreach ($linkClasses as $class) {
                if (! in_array($class, $classArray)) {
                    $classArray[] = $class;
                }
            }

            // Make sure external links open in a new tab
            if ($link->hasAttribute('href')) {
                $href = $link->getAttribute('href');
                if (strpos($href, 'http') === 0) {
                    $link->setAttribute('target', '_blank');
                    $link->setAttribute('rel', 'noopener noreferrer');
                }
            }

            $link->setAttribute('class', implode(' ', $classArray));
        }
    }

    /**
     * Process images
     */
    private function processImages(HTMLDocument $doc): void
    {
        $images = $doc->querySelectorAll('img');

        foreach ($images as $image) {
            $classes = $image->getAttribute('class');
            $classArray = ! empty($classes) ? explode(' ', $classes) : [];

            // Add Tailwind classes for images
            $imageClasses = ['max-w-full', 'h-auto', 'rounded-lg', 'my-4'];

            // Check if parent is a paragraph with text-center class
            $parent = $image->parentNode;
            $parentClasses = $parent->getAttribute('class');
            if (!empty($parentClasses) && str_contains($parentClasses, 'text-center')) {
                $imageClasses[] = 'mx-auto';
            }

            foreach ($imageClasses as $class) {
                if (! in_array($class, $classArray)) {
                    $classArray[] = $class;
                }
            }

            // Make sure images have alt text
            if (! $image->hasAttribute('alt')) {
                $image->setAttribute('alt', 'Game image');
            }

            // Add loading="lazy" for better performance
            if (! $image->hasAttribute('loading')) {
                $image->setAttribute('loading', 'lazy');
            }

            $image->setAttribute('class', implode(' ', $classArray));
        }
    }
}

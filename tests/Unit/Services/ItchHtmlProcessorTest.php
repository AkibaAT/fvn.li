<?php

declare(strict_types=1);

use App\Services\ItchHtmlProcessor;

test('process returns null for empty input', function () {
    $processor = new ItchHtmlProcessor;
    $result = $processor->process(null);
    expect($result)->toBeNull();

    $result = $processor->process('');
    expect($result)->toBeNull();
});

test('process handles basic HTML content', function () {
    $processor = new ItchHtmlProcessor;
    $html = '<p>Test paragraph</p>';
    $result = $processor->process($html);

    expect($result)->toContain('Test paragraph');
    expect($result)->toContain('class="text-gray-600 dark:text-gray-300 mb-4"');
});

test('process respects image height attributes', function () {
    $processor = new ItchHtmlProcessor;

    // Image with height attribute
    $htmlWithHeight = '<img src="test.jpg" height="200" alt="Test">';
    $resultWithHeight = $processor->process($htmlWithHeight);

    // Image without height attribute
    $htmlWithoutHeight = '<img src="test.jpg" alt="Test">';
    $resultWithoutHeight = $processor->process($htmlWithoutHeight);

    // The image with height should NOT have h-auto class and should have inline style
    expect($resultWithHeight)->toContain('height="200"');
    expect($resultWithHeight)->toContain('style="height: 200px"');
    expect($resultWithHeight)->not->toContain('h-auto');

    // The image without height should have h-auto class
    expect($resultWithoutHeight)->toContain('h-auto');
});

test('process handles centered images', function () {
    $processor = new ItchHtmlProcessor;

    // Image inside a centered div
    $html = '<div class="text-center"><img src="test.jpg" alt="Test"></div>';
    $result = $processor->process($html);

    // The image should have mx-auto class
    expect($result)->toContain('mx-auto');
});

test('process preserves existing styles when adding height', function () {
    $processor = new ItchHtmlProcessor;

    // Image with height attribute and existing style
    $html = '<img src="test.jpg" height="200" alt="Test" style="border: 1px solid red;">';
    $result = $processor->process($html);

    // The image should have both the original style and the height style
    expect($result)->toContain('style="border: 1px solid red; height: 200px"');
});

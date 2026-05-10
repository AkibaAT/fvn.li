<?php

declare(strict_types=1);

use App\Services\ItchCssProcessor;

test('process returns null for empty input', function () {
    $processor = new ItchCssProcessor;
    $result = $processor->process(null);
    expect($result)->toBeNull();

    $result = $processor->process('');
    expect($result)->toBeNull();
});

test('process handles basic CSS content', function () {
    $processor = new ItchCssProcessor;
    $css = '.test { margin: 10px; }';
    $result = $processor->process($css);

    expect($result)->toContain('margin');
    expect($result)->toContain('10px');
});

test('process removes color properties', function () {
    $processor = new ItchCssProcessor;
    $css = '.test { color: red; background-color: blue; margin: 10px; }';
    $result = $processor->process($css);

    expect($result)->not->toContain('color');
    expect($result)->not->toContain('red');
    expect($result)->not->toContain('blue');
    expect($result)->toContain('margin');
});

test('process handles CSSString values without errors', function () {
    $processor = new ItchCssProcessor;
    // CSS with string values that would create CSSString objects
    $css = '.test { content: "Hello World"; font-family: "Arial", sans-serif; }';
    $result = $processor->process($css);

    // Should not throw an error and should process successfully
    expect($result)->not->toBeNull();
    expect($result)->toContain('content');
    expect($result)->toContain('font-family');
});

test('process escapes html delimiters emitted from css strings', function () {
    $processor = new ItchCssProcessor;
    $css = '.test::before { content: "</style><script>alert(1)</script>"; margin: 10px; }';

    $result = $processor->process($css);

    expect($result)->not->toBeNull();
    expect(strtolower($result))->not->toContain('</style');
    expect(strtolower($result))->not->toContain('<script');
    expect($result)->toContain('\\3C /style\\3E ');
    expect($result)->toContain('margin');
});

test('process keeps escaped css payloads escaped for style element output', function () {
    $processor = new ItchCssProcessor;
    $css = <<<'CSS'
.test::before { content: "\3C /style\3E \3C script\3E alert(1)\3C /script\3E"; margin: 10px; }
CSS;

    $result = $processor->process($css);

    expect($result)->not->toBeNull();
    expect(strtolower($result))->not->toContain('</style');
    expect(strtolower($result))->not->toContain('<script');
    expect($result)->toContain('margin');
});

test('process handles quoted string values in various properties', function () {
    $processor = new ItchCssProcessor;
    $css = '
        .test {
            content: "test";
            font-family: "Roboto", "Helvetica", sans-serif;
            background-image: url("image.png");
        }
    ';
    $result = $processor->process($css);

    // Should process without errors
    expect($result)->not->toBeNull();
});

test('process removes gradients with color values', function () {
    $processor = new ItchCssProcessor;
    $css = '.test { background: linear-gradient(to right, red, blue); margin: 10px; }';
    $result = $processor->process($css);

    expect($result)->not->toContain('linear-gradient');
    expect($result)->not->toContain('red');
    expect($result)->toContain('margin');
});

test('process removes hex colors', function () {
    $processor = new ItchCssProcessor;
    $css = '.test { border-color: #FF0000; padding: 5px; }';
    $result = $processor->process($css);

    expect($result)->not->toContain('#FF0000');
    expect($result)->not->toContain('border-color');
    expect($result)->toContain('padding');
});

test('process removes rgb/rgba colors', function () {
    $processor = new ItchCssProcessor;
    $css = '.test { background: rgba(255, 0, 0, 0.5); width: 100px; }';
    $result = $processor->process($css);

    expect($result)->not->toContain('rgba');
    expect($result)->not->toContain('background');
    expect($result)->toContain('width');
});

test('process removes named colors', function () {
    $processor = new ItchCssProcessor;
    $css = '.test { color: red; border: 1px solid blue; margin: 10px; }';
    $result = $processor->process($css);

    expect($result)->not->toContain('red');
    expect($result)->not->toContain('blue');
    expect($result)->not->toContain('color');
    expect($result)->not->toContain('border');
    expect($result)->toContain('margin');
});

test('process handles complex CSS with mixed value types', function () {
    $processor = new ItchCssProcessor;
    $css = '
        .complex {
            margin: 10px 20px;
            padding: 5px;
            font-family: "Arial", "Helvetica", sans-serif;
            font-size: 14px;
            color: #333;
            background: linear-gradient(to bottom, white, gray);
            border: 1px solid red;
            content: "test content";
        }
    ';
    $result = $processor->process($css);

    // Should process without errors
    expect($result)->not->toBeNull();
    // Non-color properties should remain
    expect($result)->toContain('margin');
    expect($result)->toContain('padding');
    expect($result)->toContain('font-family');
    expect($result)->toContain('font-size');
    // Color-related properties should be removed
    expect($result)->not->toContain('color');
    expect($result)->not->toContain('background');
    expect($result)->not->toContain('border');
});

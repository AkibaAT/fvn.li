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

    expect($result)->toContain('.game_description .test');
    expect($result)->toContain('margin');
    expect($result)->toContain('10px');
});

test('process preserves scoped color properties', function () {
    $processor = new ItchCssProcessor;
    $css = '.test { color: red; background-color: blue; margin: 10px; }';
    $result = $processor->process($css);

    expect($result)->toContain('.game_description .test')
        ->toContain('color:red')
        ->toContain('background-color:blue')
        ->toContain('margin');
});

test('process handles CSSString values without errors', function () {
    $processor = new ItchCssProcessor;
    // CSS with string values that would create CSSString objects
    $css = '.test { content: "Hello World"; font-family: "Arial", sans-serif; }';
    $result = $processor->process($css);

    expect($result)->not->toBeNull();
    expect($result)->toContain('font-family');
    expect($result)->not->toContain('content');
});

test('process escapes html delimiters emitted from css strings', function () {
    $processor = new ItchCssProcessor;
    $css = '.test::before { content: "</style><script>alert(1)</script>"; margin: 10px; }';

    $result = $processor->process($css);

    expect(strtolower($result))->not->toContain('</style');
    expect(strtolower($result))->not->toContain('<script');
    expect($result)->toContain('margin');
    expect($result)->not->toContain('content');
});

test('process keeps escaped css payloads escaped for style element output', function () {
    $processor = new ItchCssProcessor;
    $css = <<<'CSS'
.test::before { content: "\3C /style\3E \3C script\3E alert(1)\3C /script\3E"; margin: 10px; }
CSS;

    $result = $processor->process($css);

    expect(strtolower($result))->not->toContain('</style');
    expect(strtolower($result))->not->toContain('<script');
    expect($result)->toContain('margin');
    expect($result)->not->toContain('content');
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
    expect($result)->not->toContain('content');
    expect($result)->not->toContain('background-image');
    expect($result)->not->toContain('url(');
});

test('process preserves gradients without remote resources', function () {
    $processor = new ItchCssProcessor;
    $css = '.test { background: linear-gradient(to right, red, blue); margin: 10px; }';
    $result = $processor->process($css);

    expect($result)->toContain('linear-gradient')
        ->toContain('red')
        ->toContain('blue')
        ->toContain('margin');
});

test('process preserves hex colors', function () {
    $processor = new ItchCssProcessor;
    $css = '.test { border-color: #FF0000; padding: 5px; }';
    $result = $processor->process($css);

    expect(strtolower($result))->toMatch('/#(?:f00|ff0000)\b/')
        ->and($result)->toContain('border-color')
        ->and($result)->toContain('padding');
});

test('process preserves rgb and rgba colors', function () {
    $processor = new ItchCssProcessor;
    $css = '.test { background: rgba(255, 0, 0, 0.5); width: 100px; }';
    $result = $processor->process($css);

    expect($result)->toContain('rgba')
        ->toContain('background')
        ->toContain('width');
});

test('process preserves named colors', function () {
    $processor = new ItchCssProcessor;
    $css = '.test { color: red; border: 1px solid blue; margin: 10px; }';
    $result = $processor->process($css);

    expect($result)->toContain('red')
        ->toContain('blue')
        ->toContain('color')
        ->toContain('border')
        ->toContain('margin');
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
    // Scoped visual properties should remain.
    expect($result)->not->toContain('content');
    expect($result)->toContain('color')
        ->toContain('background')
        ->toContain('border');
});

test('process preserves scoped creator layout and decoration', function () {
    $processor = new ItchCssProcessor;
    $css = <<<'CSS'
.custom-team-cards {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
}
.custom-team-card {
    display: flex;
    background: linear-gradient(#24262e, #363642);
    border: 2px solid #ddd0ae;
    clip-path: polygon(20px 0, 100% 0, 100% 100%, 0 100%);
    transition: transform 0.2s ease;
}
.custom-team-card:hover { transform: scale(1.025); }
CSS;

    $result = $processor->process($css);

    expect($result)
        ->toContain('.game_description .custom-team-cards')
        ->toContain('display:grid')
        ->toContain('grid-template-columns')
        ->toContain('display:flex')
        ->toContain('linear-gradient')
        ->toContain('border:2px solid')
        ->toContain('clip-path:polygon')
        ->toContain('transition:transform')
        ->toContain('transform:scale');
});

test('process removes itch page shell themes while preserving concrete creator backgrounds', function () {
    $processor = new ItchCssProcessor;
    $css = <<<'CSS'
.wrapper { background-color: #eee; background-repeat: repeat; }
.inner_column { color: #f81a5e; background-color: #363642; }
.view_game_page .formatted_description { background: #494955; margin: 20px; }
.custom-team-card { background: linear-gradient(#24262e, #363642); padding: 10px; }
CSS;

    $result = $processor->process($css);

    expect($result)
        ->not->toContain('.wrapper')
        ->not->toContain('.inner_column')
        ->not->toContain('.view_game_page')
        ->not->toContain('background-color:#eee')
        ->not->toContain('background-color:#363642')
        ->toContain('.game_description .custom-team-card')
        ->toContain('linear-gradient')
        ->toContain('padding:10px');
});

test('process preserves and scopes safe responsive media rules', function () {
    $processor = new ItchCssProcessor;
    $css = <<<'CSS'
.custom-team-cards { grid-template-columns: repeat(2, 1fr); }
@media (max-width: 600px) {
    .custom-team-cards {
        grid-template-columns: 1fr;
        position: fixed;
    }
}
CSS;

    $result = $processor->process($css);

    expect($result)
        ->toContain('@media (max-width: 600px)')
        ->toContain('.game_description .custom-team-cards')
        ->toContain('grid-template-columns:1fr')
        ->not->toContain('position:fixed');
});

test('process removes unsupported or malformed conditional at-rules', function () {
    $processor = new ItchCssProcessor;
    $css = <<<'CSS'
@supports (display: grid) { .supports-panel { display: grid; } }
@media (max-width: 600px) url(https://attacker.example/pixel) { .unsafe-media { display: block; } }
.safe-panel { display: block; }
CSS;

    $result = $processor->process($css);

    expect($result)
        ->toContain('.game_description .safe-panel')
        ->not->toContain('@supports')
        ->not->toContain('unsafe-media')
        ->not->toContain('attacker.example');
});

test('process scopes selectors and removes page overlay primitives', function () {
    $processor = new ItchCssProcessor;
    $css = <<<'CSS'
body::before { position: fixed; inset: 0; z-index: 99999; content: "Login"; }
.game_description { transform: scale(100); }
.panel, p.note { margin: 10px; position: fixed; }
.game_description .kept { padding: 8px; }
CSS;

    $result = $processor->process($css);

    expect($result)->toContain('.game_description .panel')
        ->toContain('.game_description p.note')
        ->toContain('.game_description .kept')
        ->toContain('margin')
        ->toContain('padding')
        ->not->toContain('body')
        ->not->toContain('scale(100)')
        ->not->toContain('position')
        ->not->toContain('z-index')
        ->not->toContain('content');
});

test('process removes remote resource loading CSS', function () {
    $processor = new ItchCssProcessor;
    $css = <<<'CSS'
.track { list-style-image: url("https://attacker.example/pixel"); margin: 1rem; }
@import url("https://attacker.example/import.css");
@font-face { font-family: x; src: url("https://attacker.example/font.woff2"); }
CSS;

    $result = $processor->process($css);

    expect($result)->toContain('margin')
        ->not->toContain('url(')
        ->not->toContain('attacker.example')
        ->not->toContain('@import')
        ->not->toContain('@font-face')
        ->not->toContain('list-style-image');
});

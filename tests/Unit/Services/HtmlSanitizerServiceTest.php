<?php

declare(strict_types=1);

use App\Services\HtmlSanitizerService;

test('sanitize css escapes html delimiters from legacy stored styles', function () {
    $sanitizer = new HtmlSanitizerService;

    $result = $sanitizer->sanitizeCss('.x::before{content:"</style><script>alert(1)</script>";margin:10px}');

    expect($result)->not->toContain('</style>')
        ->not->toContain('<script')
        ->toContain('\\3C /style>')
        ->toContain('margin:10px');
});

test('sanitize css preserves child combinator selectors', function () {
    $sanitizer = new HtmlSanitizerService;

    $result = $sanitizer->sanitizeCss('.header > .title{color:red}');

    expect($result)->toBe('.header > .title{color:red}');
});

test('sanitize description preserves safe inline image dimensions', function () {
    $sanitizer = new HtmlSanitizerService;

    $result = $sanitizer->sanitizeDescription(
        '<p><img src="https://example.com/icon.png" alt="Icon" style="width: 40px; height: 40px; max-width: 100%; position: fixed"></p>'
    );

    expect($result)
        ->toContain('style="width:40px;height:40px;max-width:100%"')
        ->not->toContain('position');
});

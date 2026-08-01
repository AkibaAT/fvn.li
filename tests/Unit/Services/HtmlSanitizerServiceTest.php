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

test('sanitize description preserves imported styling hooks', function () {
    $sanitizer = new HtmlSanitizerService;

    $result = $sanitizer->sanitizeDescription(<<<'HTML'
<div id="team" class="custom-team-cards">
    <a class="custom-team-card" href="https://example.com/profile">
        <img class="custom-team-card-avatar" src="https://example.com/avatar.png" alt="Creator">
        <span class="custom-team-card-name">Creator</span>
    </a>
</div>
HTML);

    expect($result)
        ->toContain('id="team"')
        ->toContain('class="custom-team-cards"')
        ->toContain('class="custom-team-card"')
        ->toContain('class="custom-team-card-avatar"')
        ->toContain('class="custom-team-card-name"');
});

<?php

declare(strict_types=1);

use App\Services\HtmlSanitizerService;

test('sanitize fvn review preserves formatting and limits consecutive line breaks', function () {
    $sanitizer = new HtmlSanitizerService;

    $review = "First line\r\n\r\n\r\n\r\nSecond <strong>line</strong>";

    expect($sanitizer->sanitizeFvnReview($review))->toBe("First line\n\nSecond <strong>line</strong>")
        ->and($sanitizer->sanitizeReview($review))->toBe('First line Second <strong>line</strong>');
});

test('sanitize fvn review keeps the rich editor formatting controls', function () {
    $sanitizer = new HtmlSanitizerService;
    $review = '<p style="text-align:center"><strong>Bold</strong> <em>Italic</em> <s>Strike</s> <span class="spoiler" tabindex="0" role="button" title="Click or focus to reveal spoiler">Secret</span></p><ul><li>List</li></ul><table><tbody><tr><td>Cell</td></tr></tbody></table><p><a href="https://example.com">Link</a></p>';

    expect($sanitizer->sanitizeFvnReview($review))->toBe(
        '<p style="text-align:center"><strong>Bold</strong> <em>Italic</em> <s>Strike</s> <span class="spoiler" tabindex="0" role="button" title="Click or focus to reveal spoiler">Secret</span></p><ul><li>List</li></ul><table><tbody><tr><td>Cell</td></tr></tbody></table><p><a href="https://example.com" rel="noopener">Link</a></p>'
    );
});

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

test('sanitize description preserves muted autoplay video sources', function () {
    $sanitizer = new HtmlSanitizerService;

    $result = $sanitizer->sanitizeDescription(<<<'HTML'
<video autoplay loop playsinline poster="https://example.com/clip.jpg">
    <source src="https://example.com/clip.webm" type="video/webm">
    <source src="javascript:alert(1)" type="video/mp4">
</video>
HTML);

    expect($result)
        ->toContain('autoplay')
        ->toContain('muted')
        ->toContain('src="https://example.com/clip.webm"')
        ->not->toContain('javascript:');
});

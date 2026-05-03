<?php

declare(strict_types=1);

it('escapes script-breaking characters in server-rendered json ld', function () {
    $html = view('app', [
        'page' => [
            'component' => 'games/show',
            'props' => [
                'metaTags' => [
                    'title' => 'JSON-LD XSS',
                    'structuredData' => [
                        '@type' => 'VideoGame',
                        'name' => 'Safe title',
                        'description' => '</script><script>alert("xss")</script>&\'',
                    ],
                ],
                'ziggy' => [
                    'url' => config('app.url'),
                    'port' => null,
                    'defaults' => [],
                    'routes' => [],
                    'location' => config('app.url'),
                ],
            ],
            'url' => '/games/json-ld-xss',
            'version' => null,
        ],
    ])->render();

    expect($html)->toContain('<script type="application/ld+json">')
        ->toContain('\u003C/script\u003E\u003Cscript\u003Ealert(\u0022xss\u0022)\u003C/script\u003E\u0026\u0027')
        ->not->toContain('</script><script>alert("xss")</script>');
});

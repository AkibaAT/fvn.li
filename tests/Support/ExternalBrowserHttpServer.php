<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Routing\UrlGenerator;
use Pest\Browser\Contracts\HttpServer;
use Throwable;

final class ExternalBrowserHttpServer implements HttpServer
{
    public function __construct(private readonly string $baseUrl) {}

    public function start(): void {}

    public function stop(): void {}

    public function rewrite(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/');
    }

    public function flush(): void {}

    public function bootstrap(): void
    {
        config(['app.url' => $this->baseUrl]);

        if (app()->bound('url')) {
            $urlGenerator = app('url');

            if ($urlGenerator instanceof UrlGenerator) {
                $urlGenerator->useOrigin($this->baseUrl);
                $urlGenerator->useAssetOrigin($this->baseUrl);
                $urlGenerator->forceScheme(parse_url($this->baseUrl, PHP_URL_SCHEME) ?: 'http');
            }
        }
    }

    public function lastThrowable(): ?Throwable
    {
        return null;
    }

    public function throwLastThrowableIfNeeded(): void {}
}

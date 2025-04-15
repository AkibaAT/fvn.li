<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

class ItchHttpClientFactory
{
    public function createClient(?CookieJar $cookieJar = null): Client
    {
        $config = [
            'timeout' => 30,
            'connect_timeout' => 5,
        ];

        if ($cookieJar !== null) {
            $config['cookies'] = $cookieJar;
        }

        return new Client($config);
    }

    public function createCookieJar(): CookieJar
    {
        return new CookieJar;
    }
}

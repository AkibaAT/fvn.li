<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;

if (! function_exists('canonical')) {
    function canonical(): string
    {
        $allowed = Config::get('canonical.allowed_params', []);
        $build = [];
        $query = '';

        if (is_array($allowed)) {
            foreach ($allowed as $param) {
                $value = Request::query($param);
                if ($value !== null) {
                    $build[$param] = $value;
                }
            }
        }

        if ($build !== []) {
            $query = '?' . http_build_query($build);
        }

        $baseUrl = rtrim((string) Config::get('app.url', ''), '/');

        return $baseUrl . Request::getPathInfo() . $query;
    }
}

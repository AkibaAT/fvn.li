<?php

declare(strict_types=1);

if (! function_exists('canonical')) {
    function canonical(): string
    {
        $allowed = Config::get('canonical.allowed_params');
        $build = [];
        $query = '';

        foreach ($allowed as $param) {
            if (Request::has($param)) {
                $build[$param] = Request::get($param);
            }
        }

        if (count($build)) {
            $query = '?'.http_build_query($build);
        }

        return Config::get('app.url').Request::getPathInfo().$query;
    }
}

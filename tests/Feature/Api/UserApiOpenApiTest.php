<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

it('documents every v1 route with schemas and valid local references', function () {
    $document = Yaml::parseFile(resource_path('openapi/user-api.yaml'));
    expect($document['servers'][0]['url'])->toBe('/api/v1');

    $registered = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn ($route) => collect($route->methods())
            ->reject(fn ($method) => $method === 'HEAD')
            ->map(fn ($method) => strtolower($method) . ' /' . substr($route->uri(), strlen('api/v1/'))))
        ->sort()->values()->all();
    $declared = collect($document['paths'])
        ->flatMap(fn ($operations, $path) => collect(array_keys($operations))
            ->map(fn ($method) => $method . ' ' . $path))
        ->sort()->values()->all();
    expect($declared)->toBe($registered);

    foreach ($document['paths'] as $operations) {
        foreach ($operations as $operation) {
            expect($operation)->toHaveKeys(['summary', 'x-required-ability', 'responses']);
            expect($operation['responses'])->toHaveKeys(['401', '403', '429']);
        }
    }

    $walk = function ($value) use (&$walk, $document): void {
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            if ($key === '$ref') {
                expect($item)->toStartWith('#/components/');
                $parts = explode('/', substr($item, 2));
                expect(data_get($document, implode('.', $parts)))->not->toBeNull();
            } else {
                $walk($item);
            }
        }
    };
    $walk($document);
});

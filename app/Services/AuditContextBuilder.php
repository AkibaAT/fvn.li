<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AuditContextBuilder
{
    public function build(): array
    {
        $context = [];
        $includeContext = Config::get('audit.include_context', []);

        if (request()) {
            if ($includeContext['ip_address'] ?? false) {
                $context['ip_address'] = IpAnonymizationService::getAnonymizedIpAddress(request()->ip());
            }
            if ($includeContext['user_agent'] ?? false) {
                $context['user_agent'] = request()->userAgent();
            }
            if ($includeContext['url'] ?? false) {
                $context['url'] = request()->fullUrl();
            }
            if ($includeContext['method'] ?? false) {
                $context['method'] = request()->method();
            }
            if ($includeContext['session_id'] ?? false) {
                try {
                    $session = request()->session();
                    if ($session) {
                        $context['session_id'] = $session->getId();
                    }
                } catch (RuntimeException $e) {
                    Log::debug('Audit session context unavailable', ['error' => $e->getMessage()]);
                }
            }
            if ($includeContext['request_id'] ?? false) {
                $requestId = $this->requestId();
                if ($requestId) {
                    $context['request_id'] = $requestId;
                }
            }
        }

        if (($includeContext['command'] ?? false) && app()->runningInConsole()) {
            $commandInfo = $this->commandInfo();
            if ($commandInfo) {
                $context['command'] = $commandInfo;
            }
        }

        return $context;
    }

    public function source(): string
    {
        $sources = Config::get('audit.sources', []);

        if (app()->runningInConsole()) {
            return $sources['command'] ?? 'command';
        }

        if (request()) {
            if (request()->is('api/*') || request()->expectsJson()) {
                return $sources['api'] ?? 'api';
            }

            return $sources['web'] ?? 'web';
        }

        return $sources['system'] ?? 'system';
    }

    private function requestId(): ?string
    {
        if (! request()) {
            return null;
        }

        foreach (['X-Request-ID', 'X-Correlation-ID', 'X-Trace-ID', 'Request-ID', 'Correlation-ID'] as $header) {
            $requestId = request()->header($header);
            if ($requestId) {
                return $requestId;
            }
        }

        if (request()->hasHeader('X-Laravel-Request-ID')) {
            return request()->header('X-Laravel-Request-ID');
        }

        return $this->generateRequestId();
    }

    private function generateRequestId(): string
    {
        $request = request();
        $signature = sprintf(
            '%s_%s_%s_%s_%d',
            $request->method(),
            md5($request->fullUrl()),
            IpAnonymizationService::getAnonymizedIpAddress($request->ip()),
            microtime(true),
            memory_get_usage()
        );

        return 'req_'.substr(md5($signature), 0, 16);
    }

    private function commandInfo(): ?array
    {
        if (! app()->runningInConsole()) {
            return null;
        }

        try {
            app(Kernel::class);
            $argv = $_SERVER['argv'] ?? [];
            if (empty($argv)) {
                return null;
            }

            $commandInfo = ['script' => basename($argv[0] ?? '')];
            if ($commandName = ($argv[1] ?? null)) {
                $commandInfo['name'] = $commandName;
            }

            $arguments = $this->filterSensitiveArguments(array_slice($argv, 2));
            if (! empty($arguments)) {
                $commandInfo['arguments'] = $arguments;
            }

            return $commandInfo;
        } catch (Throwable $e) {
            Log::debug('Audit console context unavailable', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function filterSensitiveArguments(array $arguments): array
    {
        $sensitivePatterns = [
            '/^--password=/',
            '/^--token=/',
            '/^--secret=/',
            '/^--key=/',
            '/^--api-key=/',
        ];

        $filtered = [];
        foreach ($arguments as $argument) {
            foreach ($sensitivePatterns as $pattern) {
                if (preg_match($pattern, $argument)) {
                    $filtered[] = preg_replace('/=.*/', '=***', $argument);

                    continue 2;
                }
            }

            $filtered[] = $argument;
        }

        return $filtered;
    }
}

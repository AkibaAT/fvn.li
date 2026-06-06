<?php

declare(strict_types=1);

namespace App\Support\Diagnostics;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Log\Logger;
use Illuminate\Log\LogManager;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monolog;
use Throwable;

class DiagnosticLogManager extends LogManager
{
    public function __construct($app)
    {
        $this->writeDiagnostic('construct', [
            'incoming_app_class' => is_object($app) ? $app::class : get_debug_type($app),
            'incoming_config_class' => $this->safeConfigClass($app),
            'trace' => $this->compactTrace(),
        ]);

        parent::__construct($app);
    }

    public function setApplication($app)
    {
        $this->writeDiagnostic('setApplication', [
            'incoming_app_class' => is_object($app) ? $app::class : get_debug_type($app),
            'incoming_config_class' => $this->safeConfigClass($app),
            'trace' => $this->compactTrace(),
        ]);

        return parent::setApplication($app);
    }

    public function getDefaultDriver()
    {
        $this->writeDiagnostic('getDefaultDriver before config access', $this->state());

        $default = $this->safeConfigValue($this->app, 'logging.default');

        if (is_string($default) || $default === null) {
            return $default ?? 'null';
        }

        $this->writeDiagnostic('getDefaultDriver using fallback', [
            'configured_default_type' => get_debug_type($default),
        ]);

        return 'null';
    }

    protected function configurationFor($name)
    {
        $this->writeDiagnostic("configurationFor({$name}) before config access", $this->state());

        $config = $this->configRepository();

        if ($config instanceof Repository) {
            $channelConfig = $config->get("logging.channels.{$name}");

            if (is_array($channelConfig)) {
                return $channelConfig;
            }
        }

        $this->writeDiagnostic("configurationFor({$name}) using fallback", $this->state());

        return match ($name) {
            'emergency' => ['path' => $this->fallbackLogPath()],
            'deprecations', 'null' => [
                'driver' => 'monolog',
                'handler' => NullHandler::class,
            ],
            default => null,
        };
    }

    protected function createEmergencyLogger()
    {
        $config = $this->configurationFor('emergency') ?? [];

        $handler = new StreamHandler(
            $config['path'] ?? $this->fallbackLogPath(),
            $this->level(['level' => 'debug']),
        );

        return new Logger(
            new Monolog('laravel', $this->prepareHandlers([$handler])),
            $this->eventsDispatcher(),
        );
    }

    protected function get($name, ?array $config = null)
    {
        try {
            if (isset($this->channels[$name])) {
                return $this->channels[$name];
            }

            $logger = $this->resolve($name, $config);

            return $this->channels[$name] = $this->tap(
                $name,
                new Logger($logger, $this->eventsDispatcher()),
            )->withContext($this->sharedContext);
        } catch (Throwable $e) {
            return tap($this->createEmergencyLogger(), function ($logger) use ($e) {
                $logger->emergency('Unable to create configured logger. Using emergency logger.', [
                    'exception' => $e,
                ]);
            });
        }
    }

    protected function parseDriver($driver)
    {
        $driver ??= $this->getDefaultDriver();

        if ($this->runningUnitTests() && $driver === null) {
            $driver = 'null';
        }

        return $driver === null ? null : trim($driver);
    }

    private function state(): array
    {
        return [
            'app_class' => is_object($this->app) ? $this->app::class : get_debug_type($this->app),
            'config_class' => $this->safeConfigClass($this->app),
            'log_default_config' => $this->safeConfigValue($this->app, 'logging.default'),
            'trace' => $this->compactTrace(),
        ];
    }

    private function configRepository(): ?Repository
    {
        try {
            if (! is_array($this->app) && ! $this->app instanceof \ArrayAccess) {
                return null;
            }

            $config = $this->app['config'] ?? null;

            return $config instanceof Repository ? $config : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function eventsDispatcher(): ?Dispatcher
    {
        try {
            if (! is_array($this->app) && ! $this->app instanceof \ArrayAccess) {
                return null;
            }

            $events = $this->app['events'] ?? null;

            return $events instanceof Dispatcher ? $events : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function runningUnitTests(): bool
    {
        try {
            return is_object($this->app)
                && method_exists($this->app, 'runningUnitTests')
                && $this->app->runningUnitTests();
        } catch (Throwable) {
            return false;
        }
    }

    private function fallbackLogPath(): string
    {
        try {
            if (is_object($this->app) && method_exists($this->app, 'storagePath')) {
                return $this->app->storagePath('logs/laravel.log');
            }
        } catch (Throwable) {
            //
        }

        return dirname(__DIR__, 3).'/storage/logs/laravel.log';
    }

    private function safeConfigClass(mixed $app): ?string
    {
        try {
            if (! is_array($app) && ! $app instanceof \ArrayAccess) {
                return null;
            }

            $config = $app['config'] ?? null;

            return is_object($config) ? $config::class : get_debug_type($config);
        } catch (Throwable $e) {
            return 'error: '.$e::class.': '.$e->getMessage();
        }
    }

    private function safeConfigValue(mixed $app, string $key): mixed
    {
        try {
            if (! is_array($app) && ! $app instanceof \ArrayAccess) {
                return null;
            }

            $config = $app['config'] ?? null;

            if (is_object($config) && method_exists($config, 'get')) {
                return $config->get($key);
            }

            if (is_array($config) || $config instanceof \ArrayAccess) {
                return $config[$key] ?? null;
            }

            return null;
        } catch (Throwable $e) {
            return 'error: '.$e::class.': '.$e->getMessage();
        }
    }

    private function compactTrace(): array
    {
        return array_map(
            static fn (array $frame): string => ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '').'@'.($frame['file'] ?? 'unknown').':'.($frame['line'] ?? '?'),
            array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 2, 8),
        );
    }

    private function writeDiagnostic(string $event, array $context): void
    {
        if (! $this->diagnosticsEnabled()) {
            return;
        }

        @file_put_contents(
            'php://stderr',
            '[Laravel log manager diagnostics] '.$event.' '.json_encode($context).PHP_EOL,
        );
    }

    private function diagnosticsEnabled(): bool
    {
        $value = $_ENV['LOG_MANAGER_DIAGNOSTICS']
            ?? $_SERVER['LOG_MANAGER_DIAGNOSTICS']
            ?? getenv('LOG_MANAGER_DIAGNOSTICS');

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Facade;
use ReflectionClass;
use ReflectionObject;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     */
    public function report(Throwable $e)
    {
        try {
            parent::report($e);
        } catch (Throwable $reportingException) {
            $this->writeLoggingDiagnostics('Laravel exception reporting failed');
            $this->writeNativeErrorLog('Laravel exception reporting failed', $reportingException);
            $this->writeNativeErrorLog('Original Laravel exception', $e);
        }
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $this->writeNativeErrorLog('Laravel exception fallback', $e);
        });
    }

    /**
     * Reports error based on report method on exception or to logger.
     */
    protected function reportThrowable(Throwable $e): void
    {
        try {
            parent::reportThrowable($e);
        } catch (Throwable $reportingException) {
            $this->writeLoggingDiagnostics('Laravel exception reportThrowable failed');
            $this->writeNativeErrorLog('Laravel exception reporting failed', $reportingException);
            $this->writeNativeErrorLog('Original Laravel exception', $e);
        }
    }

    private function writeNativeErrorLog(string $label, Throwable $e): void
    {
        $requestLabel = app()->runningInConsole()
            ? 'CLI'
            : request()->method() . ' ' . request()->path();

        error_log(sprintf(
            '[%s] %s %s: %s in %s:%d',
            $label,
            $requestLabel,
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        ));

        error_log($e->getTraceAsString());

        if ($previous = $e->getPrevious()) {
            $this->writeNativeErrorLog($label . ' previous', $previous);
        }
    }

    private function writeLoggingDiagnostics(string $label): void
    {
        $diagnostics = [
            'running_in_console' => app()->runningInConsole(),
            'container_class' => $this->container::class,
            'container_config_bound' => $this->container->bound('config'),
            'container_config_resolved' => $this->container->resolved('config'),
            'container_log_bound' => $this->container->bound('log'),
            'container_log_resolved' => $this->container->resolved('log'),
        ];

        try {
            $diagnostics['container_config_class'] = $this->container->bound('config')
                ? $this->container->make('config')::class
                : null;
        } catch (Throwable $e) {
            $diagnostics['container_config_error'] = $e::class . ': ' . $e->getMessage();
        }

        try {
            $log = $this->container->resolved('log') ? $this->container->make('log') : null;
            $diagnostics['log_manager_class'] = $log ? $log::class : null;
            $diagnostics['log_manager_app_class'] = $log ? $this->readObjectPropertyClass($log, 'app') : null;
        } catch (Throwable $e) {
            $diagnostics['log_manager_error'] = $e::class . ': ' . $e->getMessage();
        }

        try {
            $facadeApplication = $this->readStaticProperty(Facade::class, 'app');
            $diagnostics['facade_app_class'] = is_object($facadeApplication) ? $facadeApplication::class : get_debug_type($facadeApplication);
        } catch (Throwable $e) {
            $diagnostics['facade_app_error'] = $e::class . ': ' . $e->getMessage();
        }

        error_log('[Laravel logging diagnostics] ' . $label . ' ' . json_encode($diagnostics));
    }

    private function readObjectPropertyClass(object $object, string $property): string
    {
        $reflection = new ReflectionObject($object);

        while (! $reflection->hasProperty($property)) {
            $parent = $reflection->getParentClass();

            if (! $parent) {
                return 'missing-property';
            }

            $reflection = $parent;
        }

        $value = $reflection->getProperty($property)->getValue($object);

        return is_object($value) ? $value::class : get_debug_type($value);
    }

    private function readStaticProperty(string $class, string $property): mixed
    {
        $reflection = new ReflectionClass($class);

        while (! $reflection->hasProperty($property)) {
            $parent = $reflection->getParentClass();

            if (! $parent) {
                return null;
            }

            $reflection = $parent;
        }

        return $reflection->getProperty($property)->getValue();
    }
}

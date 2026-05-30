<?php

declare(strict_types=1);

namespace App\Console\Traits;

use App\Services\FlareSolverrSessionManager;
use Exception;

/**
 * Trait for commands that need to manage FlareSolverr sessions
 *
 * This trait provides automatic session management for commands that fetch
 * data from Cloudflare-protected sites like itch.io.
 *
 * Usage:
 * 1. Add `use ManagesFlareSolverrSession;` to your command class
 * 2. Wrap your handle() logic in `executeWithFlareSolverrSession()`
 *
 * Example:
 * ```php
 * public function handle(): int
 * {
 *     return $this->executeWithFlareSolverrSession(function() {
 *         // Your command logic here
 *         return 0;
 *     });
 * }
 * ```
 */
trait ManagesFlareSolverrSession
{
    /**
     * Get the command name for logging
     */
    abstract public function getName(): ?string;

    /**
     * Execute command logic with automatic FlareSolverr session management
     *
     * Creates a FlareSolverr session before executing the callback,
     * and destroys it afterwards (even if an exception is thrown).
     *
     * HTML requests are automatically routed through FlareSolverr.
     * API requests are automatically skipped (not Cloudflare-protected).
     *
     * @param  callable  $callback  The command logic to execute
     * @return int The command exit code
     */
    protected function executeWithFlareSolverrSession(callable $callback): int
    {
        /** @var FlareSolverrSessionManager $sessionManager */
        $sessionManager = app(FlareSolverrSessionManager::class);

        $commandName = $this->getName() ?? 'unknown';

        try {
            return $sessionManager->executeWithSession($commandName, $callback);
        } catch (Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");

            return 1;
        }
    }
}

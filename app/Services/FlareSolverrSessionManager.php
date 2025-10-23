<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Manages FlareSolverr sessions for command execution
 * 
 * This service creates a persistent FlareSolverr session at the start of a command
 * and destroys it when the command completes, following FlareSolverr best practices.
 */
class FlareSolverrSessionManager
{
    private ?string $activeSessionId = null;

    private bool $sessionActive = false;

    public function __construct(
        private readonly FlareSolverrClient $flareSolverr
    ) {
    }

    /**
     * Destructor - ensure session is cleaned up when object is destroyed
     */
    public function __destruct()
    {
        if ($this->sessionActive && $this->activeSessionId !== null) {
            try {
                Log::warning('FlareSolverr session not properly closed, cleaning up in destructor', [
                    'session_id' => $this->activeSessionId,
                ]);
                $this->flareSolverr->destroySession($this->activeSessionId);
            } catch (Exception $e) {
                // Silently fail in destructor
            }
        }
    }

    /**
     * Start a new FlareSolverr session for a command
     *
     * Uses the command name as the session ID to prevent cross-talk between
     * concurrent commands. Since commands use withoutOverlapping(), this ensures
     * each command has its own isolated session.
     *
     * @param string $commandName Name of the command (used as session ID)
     * @return bool True if session was created successfully
     */
    public function startSession(string $commandName): bool
    {
        if ($this->sessionActive) {
            Log::warning('FlareSolverr session already active', [
                'command' => $commandName,
                'session_id' => $this->activeSessionId,
            ]);
            return true;
        }

        try {
            Log::info('Creating FlareSolverr session for command', [
                'command' => $commandName,
            ]);

            // Use command name as session ID to prevent cross-talk
            $sessionId = $this->normalizeSessionId($commandName);
            $this->activeSessionId = $this->flareSolverr->createSession($sessionId);
            $this->sessionActive = true;

            Log::info('FlareSolverr session created', [
                'command' => $commandName,
                'session_id' => $this->activeSessionId,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to create FlareSolverr session', [
                'command' => $commandName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * End the active FlareSolverr session
     * 
     * @param string $commandName Name of the command (for logging)
     */
    public function endSession(string $commandName): void
    {
        if (!$this->sessionActive || $this->activeSessionId === null) {
            return;
        }

        try {
            Log::info('Destroying FlareSolverr session', [
                'command' => $commandName,
                'session_id' => $this->activeSessionId,
            ]);

            $this->flareSolverr->destroySession($this->activeSessionId);

            Log::info('FlareSolverr session destroyed', [
                'command' => $commandName,
                'session_id' => $this->activeSessionId,
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to destroy FlareSolverr session', [
                'command' => $commandName,
                'session_id' => $this->activeSessionId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->activeSessionId = null;
            $this->sessionActive = false;
        }
    }

    /**
     * Get the active session ID
     * 
     * @return string|null The session ID if active, null otherwise
     */
    public function getActiveSessionId(): ?string
    {
        return $this->sessionActive ? $this->activeSessionId : null;
    }

    /**
     * Check if a session is currently active
     * 
     * @return bool True if a session is active
     */
    public function isSessionActive(): bool
    {
        return $this->sessionActive;
    }

    /**
     * Execute a command with automatic session management
     *
     * Creates a session, executes the callback, and destroys the session
     * even if the callback throws an exception.
     *
     * @param string $commandName Name of the command (used as session ID)
     * @param callable $callback The command logic to execute
     * @return mixed The return value of the callback
     * @throws Exception If the callback throws an exception
     */
    public function executeWithSession(string $commandName, callable $callback): mixed
    {
        $sessionCreated = $this->startSession($commandName);

        try {
            return $callback();
        } finally {
            if ($sessionCreated) {
                $this->endSession($commandName);
            }
        }
    }

    /**
     * Normalize command name to a valid session ID
     *
     * FlareSolverr session IDs should be alphanumeric with underscores/hyphens.
     * Convert command names like "games:refresh" to "games_refresh".
     *
     * @param string $commandName The command name
     * @return string The normalized session ID
     */
    private function normalizeSessionId(string $commandName): string
    {
        // Replace colons and other special chars with underscores
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $commandName);
    }
}


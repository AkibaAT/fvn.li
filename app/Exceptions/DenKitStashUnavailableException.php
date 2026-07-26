<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * DenKit Stash is configured but a request to it failed. The stash is the
 * source of truth for stored archives, so a failing stash aborts the refresh
 * rather than degrading to a re-download from the source.
 */
final class DenKitStashUnavailableException extends RuntimeException {}

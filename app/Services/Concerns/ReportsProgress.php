<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Closure;
use Illuminate\Support\Facades\Log;

trait ReportsProgress
{
    private ?Closure $progressReporter = null;

    public function setProgressReporter(?callable $reporter): static
    {
        $this->progressReporter = $reporter === null ? null : Closure::fromCallable($reporter);

        return $this;
    }

    protected function progress(string $message): void
    {
        if ($this->progressReporter) {
            ($this->progressReporter)(rtrim($message));

            return;
        }

        Log::debug(rtrim($message));
    }
}

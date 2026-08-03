<?php

declare(strict_types=1);

namespace App\Services;

use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;

/**
 * Brings every index's embedder up to the configured state.
 *
 * Embedders are applied on their own rather than as part of the index settings
 * payload, because Meilisearch resolves a settings payload as a single task: an
 * embedder it cannot build would fail the payload and strip the index of its
 * filterable and sortable attributes as well.
 */
class MeilisearchEmbedderConfigurator
{
    public const TASK_TIMEOUT_SECONDS = 600;

    private const TASK_POLL_MILLISECONDS = 2000;

    public function __construct(private readonly Client $client) {}

    /**
     * @return array<int, array{index: string, status: string, model: string, message?: string}>
     */
    public function ensure(): array
    {
        $results = [];

        /** @var array<string, array<string, array<string, mixed>>> $configured */
        $configured = config('scout.meilisearch.index-embedders', []);

        foreach ($configured as $indexName => $desired) {
            $results[] = $this->ensureIndex((string) $indexName, $desired);
        }

        return $results;
    }

    /**
     * @param  array<string, array<string, mixed>>  $desired
     * @return array{index: string, status: string, model: string, message?: string}
     */
    private function ensureIndex(string $indexName, array $desired): array
    {
        $model = implode(', ', array_map(
            fn (array $embedder): string => (string) ($embedder['model'] ?? 'unknown'),
            $desired
        ));
        $index = $this->client->index($indexName);

        try {
            $current = $index->getEmbedders() ?? [];
        } catch (ApiException $exception) {
            if ($exception->errorCode !== 'index_not_found') {
                throw $exception;
            }

            $finished = $this->waitForTask($this->client->createIndex($indexName, ['primaryKey' => 'id']));
            if (($finished['status'] ?? null) !== 'succeeded') {
                return $this->failedResult($indexName, $model, $finished, 'index creation failed');
            }

            /** @var array<string, mixed> $settings */
            $settings = config("scout.meilisearch.index-settings.{$indexName}", []);
            if ($settings !== []) {
                $finished = $this->waitForTask($index->updateSettings($settings));
                if (($finished['status'] ?? null) !== 'succeeded') {
                    return $this->failedResult($indexName, $model, $finished, 'index settings failed');
                }
            }

            $current = [];
        }

        if ($this->isAlreadyApplied($current, $desired)) {
            return ['index' => $indexName, 'status' => 'unchanged', 'model' => $model];
        }

        $finished = $this->waitForTask($index->updateEmbedders($desired));

        if (($finished['status'] ?? null) !== 'succeeded') {
            return $this->failedResult($indexName, $model, $finished, 'embedder update failed');
        }

        return ['index' => $indexName, 'status' => 'applied', 'model' => $model];
    }

    /**
     * @param  array{taskUid: int}  $task
     * @return array<string, mixed>
     */
    private function waitForTask(array $task): array
    {
        return $this->client->waitForTask(
            $task['taskUid'],
            self::TASK_TIMEOUT_SECONDS * 1000,
            self::TASK_POLL_MILLISECONDS
        );
    }

    /**
     * @param  array<string, mixed>  $finished
     * @return array{index: string, status: string, model: string, message: string}
     */
    private function failedResult(string $indexName, string $model, array $finished, string $fallback): array
    {
        return [
            'index' => $indexName,
            'status' => 'failed',
            'model' => $model,
            'message' => $finished['error']['message'] ?? $fallback,
        ];
    }

    /**
     * Meilisearch reports an embedder with the defaults it filled in, so only the
     * configured keys are compared. Re-applying an embedder re-embeds the whole
     * index, which makes this comparison the difference between a no-op deploy
     * step and a full re-embed on every deploy.
     *
     * @param  array<string, array<string, mixed>>  $desired
     */
    private function isAlreadyApplied(array $current, array $desired): bool
    {
        foreach ($desired as $name => $embedder) {
            foreach ($embedder as $key => $value) {
                if (($current[$name][$key] ?? null) !== $value) {
                    return false;
                }
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

use App\Console\Commands\MeilisearchSetup;
use Illuminate\Console\OutputStyle;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksResults;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function invokeMeilisearchSetupVerification(MeilisearchSetup $command): bool
{
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    $method = new ReflectionMethod($command, 'verifySetup');
    $method->setAccessible(true);

    return $method->invoke($command);
}

function invokeMeilisearchSettingsWait(int $watermark): bool
{
    $command = new MeilisearchSetup;
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    $method = new ReflectionMethod($command, 'awaitSettingsTasks');
    $method->setAccessible(true);

    return $method->invoke($command, $watermark);
}

function fakeMeilisearchTaskQueue(array $pending, array $failed): void
{
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('getTasks')->andReturn(
        new TasksResults(['results' => $pending]),
        new TasksResults(['results' => $failed])
    );

    app()->instance(Client::class, $client);
}

it('waits for asynchronous Meilisearch indexing before verification succeeds', function () {
    $command = new class extends MeilisearchSetup
    {
        public int $checks = 0;

        public int $sleeps = 0;

        protected function visibleGameCount(): int
        {
            return 1;
        }

        protected function hasSearchableGameResult(): bool
        {
            $this->checks++;

            return $this->checks === 3;
        }

        protected function sleepBeforeSearchRetry(): void
        {
            $this->sleeps++;
        }
    };

    expect(invokeMeilisearchSetupVerification($command))->toBeTrue()
        ->and($command->checks)->toBe(3)
        ->and($command->sleeps)->toBe(2);
});

it('fails verification only after retrying empty Meilisearch search results', function () {
    $command = new class extends MeilisearchSetup
    {
        public int $checks = 0;

        public int $sleeps = 0;

        protected function visibleGameCount(): int
        {
            return 1;
        }

        protected function hasSearchableGameResult(): bool
        {
            $this->checks++;

            return false;
        }

        protected function sleepBeforeSearchRetry(): void
        {
            $this->sleeps++;
        }
    };

    expect(invokeMeilisearchSetupVerification($command))->toBeFalse()
        ->and($command->checks)->toBe(10)
        ->and($command->sleeps)->toBe(9);
});

it('reports settings tasks this run submitted and failed', function () {
    fakeMeilisearchTaskQueue(pending: [], failed: [
        [
            'uid' => 42,
            'indexUid' => 'games',
            'error' => ['message' => 'Error while generating embeddings'],
        ],
    ]);

    expect(invokeMeilisearchSettingsWait(41))->toBeFalse();
});

it('ignores settings tasks that failed before this run', function () {
    fakeMeilisearchTaskQueue(pending: [], failed: [
        [
            'uid' => 12,
            'indexUid' => 'games',
            'error' => ['message' => 'Error while generating embeddings'],
        ],
    ]);

    expect(invokeMeilisearchSettingsWait(41))->toBeTrue();
});

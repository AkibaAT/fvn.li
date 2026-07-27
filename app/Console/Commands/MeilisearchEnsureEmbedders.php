<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MeilisearchEmbedderConfigurator;
use Illuminate\Console\Command;

class MeilisearchEnsureEmbedders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meilisearch:embedders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure every Meilisearch index has its configured embedder';

    public function handle(MeilisearchEmbedderConfigurator $configurator): int
    {
        $results = $configurator->ensure();
        $failed = false;

        foreach ($results as $result) {
            $index = $result['index'];
            $model = $result['model'];

            match ($result['status']) {
                'unchanged' => $this->line("  [{$index}] embedder already active ({$model})"),
                'applied' => $this->info("  [{$index}] embedder applied ({$model})"),
                default => $this->reportFailure($index, $model, $result['message'] ?? ''),
            };

            $failed = $failed || $result['status'] === 'failed';
        }

        if ($failed) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function reportFailure(string $index, string $model, string $message): void
    {
        $this->error("  [{$index}] Meilisearch rejected the embedder ({$model}): {$message}");
        $this->line('     The index keeps its attribute settings; only vector search is unavailable.');
        $this->line('     The huggingFace source needs a model whose architecture is BertModel or ModernBert.');
    }
}

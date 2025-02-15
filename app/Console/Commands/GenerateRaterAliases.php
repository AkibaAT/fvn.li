<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Rater;
use App\Services\RaterAliasService;
use Illuminate\Console\Command;

class GenerateRaterAliases extends Command
{
    protected $signature = 'raters:generate-aliases {--chunk=1000}';
    protected $description = 'Generate aliases for raters that don\'t have one';

    public function handle(RaterAliasService $aliasService): int
    {
        $total = Rater::whereNull('alias')->count();

        if ($total === 0) {
            $this->info('All raters already have aliases.');

            return 0;
        }

        $this->info("Generating aliases for {$total} raters...");
        $bar = $this->output->createProgressBar($total);
        $chunkSize = $this->option('chunk') || 1000;

        Rater::whereNull('alias')
            ->chunkById(intval($chunkSize), function ($raters) use ($aliasService, $bar) {
                foreach ($raters as $rater) {
                    $maxAttempts = 10;

                    // Try regular alias generation first
                    for ($i = 0; $i < $maxAttempts; $i++) {
                        $alias = $aliasService->generateAlias();
                        if (! Rater::where('alias', $alias)->exists()) {
                            $rater->alias = $alias;
                            $rater->save();
                            break;
                        }
                    }

                    // If all attempts fail, use the fallback unique generation
                    if (! $rater->alias) {
                        $rater->alias = $aliasService->generateUniqueAlias();
                        $rater->save();
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info('Aliases generated successfully.');

        return 0;
    }
}

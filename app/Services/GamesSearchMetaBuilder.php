<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Seo\MetaTags;
use Illuminate\Http\Request;

class GamesSearchMetaBuilder
{
    public function build(
        Request $request,
        ?string $search,
        mixed $selectedStatuses,
        mixed $selectedEngines,
        mixed $selectedPlatforms,
        mixed $selectedLanguages,
        mixed $selectedTags,
        int $totalGames,
        array $filterOptions,
        mixed $games
    ): MetaTags {
        $titleParts = [];
        $descriptionParts = [];

        if (! empty($search)) {
            $titleParts[] = "Search: {$search}";
            $descriptionParts[] = "Search results for '{$search}'";
        }

        $this->appendPlatformParts($selectedPlatforms, $filterOptions, $titleParts, $descriptionParts);
        $this->appendLanguageParts($selectedLanguages, $filterOptions, $titleParts, $descriptionParts);
        $this->appendStatusParts($selectedStatuses, $titleParts, $descriptionParts);
        $this->appendEngineParts($selectedEngines, $titleParts, $descriptionParts);
        $this->appendTagParts($selectedTags, $filterOptions, $titleParts, $descriptionParts);
        $this->appendBooleanFilterParts($request, $titleParts, $descriptionParts);

        $description = count($descriptionParts) > 0
            ? implode('. ', $descriptionParts).' - Browse and discover visual novels on FVN.li'
            : 'Browse and discover visual novels on FVN.li';

        if ($totalGames > 0) {
            $description .= sprintf(' - %d games found', $totalGames);
        }

        $description = $this->appendExampleGames($description, $games, $totalGames);

        return new MetaTags(
            browserTitle: 'Visual Novels',
            socialTitle: count($titleParts) > 0
                ? implode(' - ', array_slice($titleParts, 0, 3)).' Visual Novels'
                : 'Visual Novels',
            description: $description,
            image: asset(config('social.images.games_list', config('social.images.default'))),
            url: $request->url(),
        );
    }

    private function appendPlatformParts(mixed $selectedPlatforms, array $filterOptions, array &$titleParts, array &$descriptionParts): void
    {
        if (! $selectedPlatforms) {
            return;
        }

        $platforms = is_array($selectedPlatforms) ? $selectedPlatforms : explode(',', $selectedPlatforms);
        $platformLabels = array_map(fn ($platform) => $filterOptions['platforms'][$platform] ?? ucfirst($platform), $platforms);

        if (count($platformLabels) > 0) {
            $titleParts[] = implode(', ', $platformLabels);
            $descriptionParts[] = 'Available on '.implode(', ', $platformLabels);
        }
    }

    private function appendLanguageParts(mixed $selectedLanguages, array $filterOptions, array &$titleParts, array &$descriptionParts): void
    {
        if (! $selectedLanguages) {
            return;
        }

        $languages = is_array($selectedLanguages) ? $selectedLanguages : explode(',', $selectedLanguages);
        $languageLabels = array_map(fn ($language) => $filterOptions['languages'][$language]['ref_name'] ?? $language, $languages);

        if (count($languageLabels) > 0 && count($languageLabels) <= 3) {
            $titleParts[] = implode(', ', $languageLabels);
            $descriptionParts[] = 'In '.implode(', ', $languageLabels);
        }
    }

    private function appendStatusParts(mixed $selectedStatuses, array &$titleParts, array &$descriptionParts): void
    {
        if (! $selectedStatuses) {
            return;
        }

        $statuses = is_array($selectedStatuses) ? $selectedStatuses : explode(',', $selectedStatuses);
        if (count($statuses) === 1) {
            $status = ucwords(str_replace('_', ' ', $statuses[0]));
            $titleParts[] = $status;
            $descriptionParts[] = "Status: {$status}";
        }
    }

    private function appendEngineParts(mixed $selectedEngines, array &$titleParts, array &$descriptionParts): void
    {
        if (! $selectedEngines) {
            return;
        }

        $engines = is_array($selectedEngines) ? $selectedEngines : explode(',', $selectedEngines);
        if (count($engines) === 1) {
            $titleParts[] = $engines[0];
            $descriptionParts[] = "Made with {$engines[0]}";
        }
    }

    private function appendTagParts(mixed $selectedTags, array $filterOptions, array &$titleParts, array &$descriptionParts): void
    {
        if (! $selectedTags) {
            return;
        }

        $tags = is_array($selectedTags) ? $selectedTags : explode(',', $selectedTags);
        $tagLabels = [];
        foreach (array_slice($tags, 0, 2) as $tagId) {
            $tagLabel = $filterOptions['tags'][$tagId] ?? null;
            if ($tagLabel) {
                $tagLabels[] = preg_replace('/\s*\(\d+\)$/', '', $tagLabel);
            }
        }

        if (count($tagLabels) > 0) {
            $titleParts[] = implode(', ', $tagLabels);
            $descriptionParts[] = 'Tagged: '.implode(', ', $tagLabels);
        }
    }

    private function appendBooleanFilterParts(Request $request, array &$titleParts, array &$descriptionParts): void
    {
        $nsfw = $request->boolean('nsfw');
        $sfw = $request->boolean('sfw');
        if ($nsfw && ! $sfw) {
            $titleParts[] = 'NSFW';
            $descriptionParts[] = 'NSFW content';
        } elseif ($sfw && ! $nsfw) {
            $titleParts[] = 'SFW';
            $descriptionParts[] = 'SFW content only';
        }

        $showPaid = $request->boolean('showPaid');
        $showFree = $request->boolean('showFree');
        if ($showPaid && ! $showFree) {
            $titleParts[] = 'Paid';
            $descriptionParts[] = 'Paid games';
        } elseif ($showFree && ! $showPaid) {
            $titleParts[] = 'Free';
            $descriptionParts[] = 'Free games';
        }

        if ($request->boolean('showDemo')) {
            $titleParts[] = 'Demos';
            $descriptionParts[] = 'Games with demos available';
        }
    }

    private function appendExampleGames(string $description, mixed $games, int $totalGames): string
    {
        if (! $games || $games->count() <= 0) {
            return $description;
        }

        $gameNames = collect($games->items())
            ->take(3)
            ->pluck('name')
            ->toArray();

        if (count($gameNames) === 0) {
            return $description;
        }

        $description .= '. Including: '.implode(', ', $gameNames);
        if ($totalGames > count($gameNames)) {
            $description .= ', and more';
        }

        return $description;
    }
}

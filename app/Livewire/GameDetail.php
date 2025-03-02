<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Rating;
use App\Traits\HasSocialMetaTags;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class GameDetail extends Component
{
    use HasSocialMetaTags, WithPagination;

    public Game $game;

    public bool $showAllRatings = false;

    public int $reviewsPage = 1;

    public int $versionsPage = 1;

    public ?int $selectedRating = null;

    public string|int $versionsPerPage = 5;

    public string|int $reviewsPerPage = 5;

    public ?array $characterStats = null;

    public ?int $selectedVersionId = null;

    public ?GameVersion $selectedVersion = null;

    protected array $validPerPageValues = [5, 10, 25];

    protected $queryString = [
        'showAllRatings' => ['except' => false],
        'reviewsPage' => ['except' => 1],
        'versionsPage' => ['except' => 1],
        'versionsPerPage' => ['except' => 5],
        'reviewsPerPage' => ['except' => 5],
        'selectedRating' => ['except' => null],
    ];

    public function mount(Game $game): void
    {
        $this->game = $game;
        $this->normalizePerPage('versionsPerPage');
        $this->normalizePerPage('reviewsPerPage');
    }

    public function updated($name): void
    {
        if (in_array($name, ['versionsPerPage', 'reviewsPerPage'])) {
            $this->normalizePerPage($name);
        }
    }

    public function toggleRatingsView(): void
    {
        $this->showAllRatings = ! $this->showAllRatings;
        $this->selectedRating = null;
        $this->reviewsPage = 1;
        $this->setPage(1, 'reviewsPage');
    }

    public function updatedSelectedRating(): void
    {
        $this->reviewsPage = 1;
        $this->setPage(1, 'reviewsPage');
    }

    public function render(): View
    {
        // Eager load all the relationships we need
        $this->game->load([
            'latestVersion.languageStats.language',
            'latestVersion.supportedLanguages.language',
        ]);

        $reviews = $this->game->ratings()
            ->where('is_visible', true)
            ->when(! $this->showAllRatings, fn ($query) => $query->where('is_reviewed', true))
            ->when($this->selectedRating !== null, fn ($query) => $query->where('rating', $this->selectedRating))
            ->with('rater')
            ->orderByDesc('published_at')
            ->paginate($this->reviewsPerPage, pageName: 'reviewsPage');

        $availableRatings = $this->game->ratings()
            ->where('is_visible', true)
            ->when(! $this->showAllRatings, fn ($query) => $query->where('is_reviewed', true))
            ->distinct()
            ->pluck('rating')
            ->sort()
            ->values();

        // Paginate game versions
        $versions = $this->game->gameVersions()
            ->with([
                'supportedLanguages.language',
                'languageStats.language',
            ])
            ->paginate($this->versionsPerPage, pageName: 'versionsPage');

        $latestVersion = $this->game->latestVersion;
        $supportedLanguages = $latestVersion?->supportedLanguages
            ->map(fn ($sl) => [
                'iso_code' => $sl->iso_code,
                'ref_name' => $sl->language->ref_name,
                'flag_code' => $sl->language->flag_code,
            ])
            ?? collect();

        $versionCharacterCounts = [];
        if ($latestVersion) {
            $versionCharacterCounts[$latestVersion->id] = Character::countUniqueCharactersInLanguage(
                $this->game->id,
                $this->game->source_language_id,
                $latestVersion->id
            );
        }
        foreach ($versions as $version) {
            if ($version->id === $latestVersion->id) {
                continue;
            }
            $versionCharacterCounts[$version->id] = Character::countUniqueCharactersInLanguage(
                $this->game->id,
                $this->game->source_language_id,
                $version->id
            );
        }

        $metaTags = $this->getMetaTags();
        app('view')->share('metaTags', $metaTags);
        $this->updateMeta($metaTags);

        // Pass the latestVersion data explicitly to make it clear we're using version data
        $latestVersion = $this->game->latestVersion;

        return view('livewire.game-detail', [
            'reviews' => $reviews,
            'versions' => $versions,
            'latestVersion' => $latestVersion,
            'platforms' => [
                'windows' => $latestVersion?->is_windows ?? false,
                'linux' => $latestVersion?->is_linux ?? false,
                'mac' => $latestVersion?->is_mac ?? false,
                'android' => $latestVersion?->is_android ?? false,
                'web' => $latestVersion?->is_web ?? false,
            ],
            'rating' => $latestVersion?->rating,
            'ratingCount' => $latestVersion?->rating_count,
            'devlog' => $latestVersion?->devlog,
            'englishStats' => $latestVersion?->getStatsForLanguage('eng'),
            'supportedLanguages' => $supportedLanguages,
            'metaTags' => $this->getMetaTags(),
            'availableRatings' => $availableRatings,
            'versionCharacterCounts' => $versionCharacterCounts,
        ])
            ->layout('components.layouts.app', [
                'metaTags' => $metaTags,
                'noindex' => ! $this->game->is_visible,
            ]);
    }

    public function getMetaTags(): array
    {
        // Get latest version
        $latestVersion = $this->game->latestVersion;

        // Prepare basic game info for description
        $descriptionParts = [];

        if ($this->game->is_visible) {
            if ($this->game->status) {
                $descriptionParts[] = "A {$this->game->status} game";
            }

            if ($this->game->game_engine && $this->game->game_engine !== 'unknown') {
                $descriptionParts[] = "made with {$this->game->game_engine}";
            }

            // Add platforms from latest version
            $platforms = [];
            if ($latestVersion) {
                if ($latestVersion->is_windows) {
                    $platforms[] = 'Windows';
                }
                if ($latestVersion->is_linux) {
                    $platforms[] = 'Linux';
                }
                if ($latestVersion->is_mac) {
                    $platforms[] = 'Mac';
                }
                if ($latestVersion->is_android) {
                    $platforms[] = 'Android';
                }
                if ($latestVersion->is_web) {
                    $platforms[] = 'Web';
                }
            }
            if (! empty($platforms)) {
                $descriptionParts[] = 'available on ' . implode(', ', $platforms);
            }

            // Add word count from latest version if available
            $englishWordCount = $this->game->getEnglishWordCount();
            if ($englishWordCount) {
                $descriptionParts[] = number_format($englishWordCount) . ' words long';
            }

            // Add rating from latest version if available
            if ($latestVersion?->rating_count) {
                $descriptionParts[] = 'rated ' . number_format($latestVersion->rating_count) . ' times';
            }
        } else {
            // Get rating count from ratings table
            $ratingCount = Rating::where('game_id', $this->game->id)->where('is_visible', true)->count();

            $descriptionParts[] = 'An unlisted game rated ' . number_format($ratingCount) . ' times';
        }

        // Truncate description to around 160 characters
        $description = implode(', ', $descriptionParts) . '.';
        $description = substr($description, 0, 160);

        return [
            'title' => $this->game->name . ' - ' . config('app.name'),
            'description' => $description,
            'image' => $this->game->thumb_url ?: asset('favicon.ico'),
        ];
    }

    public function showCharacterStats(int $versionId): void
    {
        $this->selectedVersionId = $versionId;

        // Get all character stats with relationships
        $characterStats = GameVersion::find($versionId)
            ->characterStats()
            ->where('iso_code', 'not like', 'q%')
            ->with(['character', 'language'])
            ->get();

        // Get unique languages
        $languages = $characterStats
            ->sortBy('language.ref_name')
            ->unique('language.id')
            ->values()
            ->map(fn ($stat) => [
                'id' => $stat->language->id,
                'name' => $stat->language->ref_name,
                'flag' => $stat->language->flag_code,
            ]);

        // Create word count matrix (character x language)
        $characters = [];
        $wordCounts = [];
        foreach ($characterStats as $stat) {
            $displayName = $stat->character->getDisplayName($this->game->source_language_id);
            $characters[$displayName] = $displayName;
            if (! isset($wordCounts[$displayName][$stat->language->id])) {
                $wordCounts[$displayName][$stat->language->id] = 0;
            }
            $wordCounts[$displayName][$stat->language->id] += $stat->words;
        }
        sort($characters, SORT_NATURAL | SORT_FLAG_CASE);

        // Calculate totals per language
        $languageTotals = [];
        foreach ($characterStats as $stat) {
            if (! isset($languageTotals[$stat->language->id])) {
                $languageTotals[$stat->language->id] = 0;
            }
            $languageTotals[$stat->language->id] += $stat->words;
        }

        $this->characterStats = [
            'characters' => $characters,
            'languages' => $languages,
            'wordCounts' => $wordCounts,
            'languageTotals' => $languageTotals,
        ];

        $this->dispatch('open-dialog', dialogId: "character-stats-{$versionId}");
    }

    public function showFileStats(int $versionId): void
    {
        $this->selectedVersionId = $versionId;

        $this->selectedVersion = $this->game->gameVersions()
            ->with([
                'fileCategories.fileTypes' => function ($query) {
                    $query->orderBy('extension');
                },
            ])
            ->find($versionId);

        $this->dispatch('open-dialog', dialogId: 'file-stats');
    }

    protected function normalizePerPage(string $property): void
    {
        $intValue = filter_var($this->{$property}, FILTER_VALIDATE_INT);

        if ($intValue === false || ! in_array($intValue, $this->validPerPageValues)) {
            $this->{$property} = $this->validPerPageValues[0];

            return;
        }

        $this->{$property} = $intValue;
    }

    protected function updateMeta(array $metaTags): void
    {
        if (method_exists($this, 'dispatch')) {
            $this->dispatch('updateMetaTags', metaTags: $metaTags);
        }
    }

    protected function encodeFilterValue(string $value): string
    {
        return rawurlencode($value);
    }

    protected function decodeFilterValue(string $value): string
    {
        return rawurldecode($value);
    }
}

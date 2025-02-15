<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Rating;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class RatingTrends extends Component
{
    /**
     * @var array{
     *     monthly_trend: array<array{month: string, count: int}>,
     *     visible_games_monthly_trend: array<array{month: string, count: int}>
     * }
     */
    public array $ratingStats;

    public function mount(): void
    {
        $this->loadRatingStats();
    }

    public function render(): View
    {
        return view('livewire.rating-trends');
    }

    private function loadRatingStats(): void
    {
        // Cache for 5 minutes since this data is used in charts
        $this->ratingStats = Cache::remember('rating_trends.stats', now()->addMinutes(5), function () {
            $monthlyTrend = DB::table('ratings')
                ->select(DB::raw('DATE_TRUNC(\'month\', published_at) as month'), DB::raw('COUNT(*) as count'))
                ->where('is_visible', true)
                ->where(DB::raw('DATE_TRUNC(\'month\', published_at)'), '<', DB::raw('DATE_TRUNC(\'month\', CURRENT_DATE)'))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->toArray();

            $visibleGamesMonthlyTrend = Rating::query()
                ->selectRaw('DATE_TRUNC(\'month\', published_at) as month')
                ->selectRaw('COUNT(*) as count')
                ->where('is_visible', true)
                ->where(DB::raw('DATE_TRUNC(\'month\', published_at)'), '<', DB::raw('DATE_TRUNC(\'month\', CURRENT_DATE)'))
                ->whereHas('game', function ($query) {
                    $query->where('is_visible', true);
                })
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->toArray();

            return [
                'monthly_trend' => $monthlyTrend,
                'visible_games_monthly_trend' => $visibleGamesMonthlyTrend,
            ];
        });
    }
}

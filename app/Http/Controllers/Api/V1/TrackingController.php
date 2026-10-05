<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserApi\TrackingResource;
use App\Models\Game;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Services\VnListCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TrackingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $rows = $request->user()->gameProgress()->with('game')
            ->whereHas('game', fn ($q) => $q->where('is_visible', true))
            ->orderBy('game_id')->paginate(20)->withQueryString();

        return TrackingResource::collection($rows);
    }

    public function show(Request $request, int $game): TrackingResource
    {
        $this->visibleGame($game);
        $row = $request->user()->gameProgress()->with('game')->where('game_id', $game)->firstOrFail();

        return new TrackingResource($row);
    }

    public function update(Request $request, int $game): TrackingResource
    {
        $visible = $this->visibleGame($game);
        $input = $request->validate([
            'game_version_id' => ['sometimes', 'nullable', Rule::exists('game_versions', 'id')->where('game_id', $game)],
            'status' => ['sometimes', 'nullable', 'in:reading,completed,plan_to_read,on_hold,dropped'],
            'started_at' => ['sometimes', 'nullable', 'date'],
            'completed_at' => ['sometimes', 'nullable', 'date'],
            'personal_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_receiving_updates' => ['sometimes', 'boolean'],
        ]);
        if ($input === []) {
            throw ValidationException::withMessages(['body' => 'Provide at least one tracking field.']);
        }
        if ($visible->is_paid && ($input['is_receiving_updates'] ?? false)) {
            throw ValidationException::withMessages(['is_receiving_updates' => 'Updates are unavailable for paid games.']);
        }
        $row = UserGameProgress::updateOrCreate(
            ['user_id' => $request->user()->id, 'game_id' => $game],
            $input,
        );
        if (VnList::query()->where('user_id', $request->user()->id)->where('is_public', true)
            ->whereHas('entries', fn ($q) => $q->where('game_id', $game))->exists()) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return new TrackingResource($row->load('game'));
    }

    private function visibleGame(int $game): Game
    {
        return Game::query()->where('is_visible', true)->findOrFail($game);
    }
}

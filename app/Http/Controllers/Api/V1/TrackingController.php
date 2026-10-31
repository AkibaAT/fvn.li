<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserApi\TrackingResource;
use App\Models\Game;
use App\Services\UserGameProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TrackingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $rows = $request->user()->gameProgress()->with('game')
            ->whereHas('game', fn ($q) => $q->visible())
            ->orderBy('game_id')->paginate(20)->withQueryString();

        return TrackingResource::collection($rows);
    }

    public function show(Request $request, int $game): TrackingResource
    {
        Game::visible()->findOrFail($game);
        $row = $request->user()->gameProgress()->with('game')->where('game_id', $game)->firstOrFail();

        return new TrackingResource($row);
    }

    public function update(Request $request, int $game, UserGameProgressService $progress): TrackingResource
    {
        $visible = Game::visible()->findOrFail($game);
        $input = $request->validate([
            'game_version_id' => ['sometimes', 'nullable', 'integer', Rule::exists('game_versions', 'id')->where('game_id', $game)],
            'status' => ['sometimes', 'string', 'in:reading,completed,plan_to_read,on_hold,dropped'],
            'started_at' => ['sometimes', 'nullable', 'date'],
            'completed_at' => ['sometimes', 'nullable', 'date'],
            'personal_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_receiving_updates' => ['sometimes', 'boolean'],
        ]);
        if ($input === []) {
            throw ValidationException::withMessages(['body' => 'Provide at least one tracking field.']);
        }
        if (! $visible->canReceiveUpdates() && ($input['is_receiving_updates'] ?? false)) {
            throw ValidationException::withMessages(['is_receiving_updates' => $visible->is_paid ? 'Updates are unavailable for paid games.' : 'Updates are unavailable for delisted games.']);
        }

        return new TrackingResource($progress->save($request->user()->id, $visible, $input)->setRelation('game', $visible));
    }
}

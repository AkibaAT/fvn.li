<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserApi\GameResource;
use App\Models\Game;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class GameController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $input = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'platform' => ['sometimes', 'in:itch_io,steam,other'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = Game::visible()
            ->when($input['platform'] ?? null, fn ($q, $platform) => $q->where('platform', $platform));
        if ($term = trim($input['q'] ?? '')) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
            $query->where(fn ($q) => $q->whereRaw('name::text ilike ?', [$like])
                ->orWhereRaw("regexp_replace(authors::text, '<[^>]*>', '', 'g') ilike ?", [$like]));
        }

        return GameResource::collection($query->orderBy('id')->paginate($input['per_page'] ?? 20)->withQueryString());
    }

    public function show(int $game): GameResource
    {
        return new GameResource(Game::visible()->findOrFail($game));
    }

    public function resolve(Request $request): GameResource|JsonResponse
    {
        [$input, $errors] = $this->checkSelector($request->all());
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $matches = $this->matches($input);

        if ($matches->isEmpty()) {
            return response()->json(['message' => 'Game not found.'], 404);
        }
        if ($matches->count() > 1) {
            return response()->json(['message' => 'Identifier matches multiple games.'], 409);
        }

        return new GameResource($matches->first());
    }

    public function resolveBatch(Request $request): JsonResponse
    {
        $request->validate(['items' => ['required', 'array', 'list', 'min:1', 'max:50']]);
        $results = [];
        foreach ($request->input('items') as $item) {
            [$selector, $errors] = is_array($item)
                ? $this->checkSelector($item)
                : [[], ['item' => ['Each item must be an object.']]];
            if ($errors !== []) {
                $results[] = ['input' => is_array($item) ? $item : null, 'status' => 'invalid', 'errors' => $errors, 'game' => null];

                continue;
            }
            $matches = $this->matches($selector);
            $results[] = [
                'input' => $selector,
                'status' => $matches->isEmpty() ? 'unmatched' : ($matches->count() > 1 ? 'ambiguous' : 'matched'),
                'game' => $matches->count() === 1 ? (new GameResource($matches->first()))->toArray($request) : null,
            ];
        }

        return response()->json(['data' => $results]);
    }

    /** @return array{0: array<string, mixed>, 1: array<string, list<string>>} */
    private function checkSelector(array $value): array
    {
        $validator = validator($value, [
            'itch_id' => ['sometimes', 'integer', 'min:1'],
            'steam_app_id' => ['sometimes', 'integer', 'min:1'],
            'url' => ['sometimes', 'string', 'url:http,https', 'max:2048'],
        ]);
        if ($validator->fails()) {
            return [[], $validator->errors()->toArray()];
        }
        $input = $validator->validated();
        if (count($input) !== 1) {
            return [[], ['selector' => ['Provide exactly one of itch_id, steam_app_id, or url.']]];
        }
        if (isset($input['url'])) {
            $url = parse_url($input['url']);
            if (isset($url['user']) || isset($url['pass'])) {
                return [[], ['url' => ['URLs with credentials cannot be resolved.']]];
            }
        }

        return [$input, []];
    }

    private function matches(array $selector): Collection
    {
        $query = Game::visible();
        if (isset($selector['itch_id'])) {
            $query->where('itch_id', $selector['itch_id']);
        } elseif (isset($selector['steam_app_id'])) {
            $query->where('steam_app_id', $selector['steam_app_id']);
        } else {
            $url = parse_url($selector['url']);
            $normalized = strtolower($url['host']) . (isset($url['port']) ? ':' . $url['port'] : '')
                . rtrim($url['path'] ?? '', '/');
            $query->where(function ($q) use ($normalized) {
                foreach (['itch_io', 'steam', 'other'] as $platform) {
                    $value = "url->>'{$platform}'";
                    $clean = "trim(trailing '/' from split_part(split_part(regexp_replace({$value}, '^https?://', '', 'i'), '?', 1), '#', 1))";
                    $host = "split_part({$clean}, '/', 1)";
                    $q->orWhereRaw("lower({$host}) || substr({$clean}, length({$host}) + 1) = ?", [$normalized]);
                }
            });
        }

        return $query->limit(2)->get();
    }
}

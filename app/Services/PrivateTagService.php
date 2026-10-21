<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\PrivateTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class PrivateTagService
{
    public const NAME_RULES = ['name' => ['required', 'string', 'max:64']];

    private const DUPLICATE_NAME = 'You already have a tag with this name.';

    /** @return Collection<int, PrivateTag> */
    public function all(User $user): Collection
    {
        return $user->privateTags()->withCount($this->visibleGamesCount())->orderBy('name')->get();
    }

    /** @return Collection<int, PrivateTag> */
    public function forGame(User $user, int $game): Collection
    {
        Game::visible()->findOrFail($game);

        return $user->privateTags()->whereHas('games', fn (Builder $q) => $q->whereKey($game))
            ->withCount($this->visibleGamesCount())->orderBy('name')->get();
    }

    public function owned(User $user, int $tag): PrivateTag
    {
        return $user->privateTags()->findOrFail($tag);
    }

    public function visibleGames(PrivateTag $tag): BelongsToMany
    {
        return $tag->games()->visible()->orderBy('games.id');
    }

    public function create(User $user, string $name): PrivateTag
    {
        $this->ensureUniqueName($user->id, $name);
        try {
            $tag = $user->privateTags()->create(['name' => $name]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => self::DUPLICATE_NAME]);
        }

        return $tag->loadCount($this->visibleGamesCount());
    }

    public function rename(PrivateTag $tag, string $name): PrivateTag
    {
        $this->ensureUniqueName($tag->user_id, $name, $tag->id);
        try {
            $tag->update(['name' => $name]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => self::DUPLICATE_NAME]);
        }

        return $tag->loadCount($this->visibleGamesCount());
    }

    public function attach(PrivateTag $tag, int $game): PrivateTag
    {
        Game::visible()->findOrFail($game);
        $tag->games()->syncWithoutDetaching([$game]);

        return $tag->loadCount($this->visibleGamesCount());
    }

    public function detach(PrivateTag $tag, int $game): void
    {
        $tag->games()->detach($game);
    }

    /** @return array{id: int, name: string, games_count: int} */
    public function data(PrivateTag $tag): array
    {
        return ['id' => $tag->id, 'name' => $tag->name, 'games_count' => $tag->games_count];
    }

    private function ensureUniqueName(int $userId, string $name, ?int $except = null): void
    {
        if (PrivateTag::query()->where('user_id', $userId)->whereRaw('lower(name) = lower(?)', [$name])
            ->when($except, fn ($q) => $q->whereKeyNot($except))->exists()) {
            throw ValidationException::withMessages(['name' => self::DUPLICATE_NAME]);
        }
    }

    /** @return array<string, callable> */
    private function visibleGamesCount(): array
    {
        return ['games' => fn (Builder $q) => $q->visible()];
    }
}

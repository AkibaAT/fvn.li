<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getItchioUsername(): ?string
    {
        $itchioAccount = $this->socialAccounts()
            ->where('provider_name', 'itchio')
            ->first();

        if (! $itchioAccount || ! $itchioAccount->provider_data) {
            return null;
        }

        return $itchioAccount->provider_data['username'] ?? null;
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function ownsGame(Game $game): bool
    {
        return $this->ownedGamesQuery()->whereKey($game->id)->exists();
    }

    public function getItchioUrl(): ?string
    {
        $itchioAccount = $this->socialAccounts()
            ->where('provider_name', 'itchio')
            ->first();

        if (! $itchioAccount || ! $itchioAccount->provider_data) {
            return null;
        }

        return $itchioAccount->provider_data['url'] ?? null;
    }

    public function getOwnedGames()
    {
        return $this->ownedGamesQuery()->where('is_visible', true)->orderBy('name')->get();
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function gameProgress(): HasMany
    {
        return $this->hasMany(UserGameProgress::class);
    }

    public function notificationHistory(): HasMany
    {
        return $this->hasMany(NotificationHistory::class);
    }

    public function notificationPreferences(): HasOne
    {
        return $this->hasOne(UserNotificationPreferences::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function additionRequests(): BelongsToMany
    {
        return $this->belongsToMany(AdditionRequest::class, 'addition_request_users')
            ->withTimestamps()
            ->orderBy('addition_request_users.created_at', 'desc');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function raters(): HasMany
    {
        return $this->hasMany(Rater::class);
    }

    /**
     * Initialize default VN lists for a new user.
     */
    public function initializeDefaultLists(): void
    {
        $defaultLists = [
            ['name' => 'Currently Reading', 'type' => 'reading', 'is_default' => true],
            ['name' => 'Completed', 'type' => 'completed', 'is_default' => true],
            ['name' => 'Plan to Read', 'type' => 'plan_to_read', 'is_default' => true],
            ['name' => 'On Hold', 'type' => 'on_hold', 'is_default' => true],
            ['name' => 'Dropped', 'type' => 'dropped', 'is_default' => true],
        ];

        foreach ($defaultLists as $list) {
            $this->vnLists()->create($list);
        }
    }

    public function vnLists(): HasMany
    {
        return $this->hasMany(VnList::class)->orderBy('created_at', 'desc');
    }

    public function privateTags(): HasMany
    {
        return $this->hasMany(PrivateTag::class);
    }

    /**
     * @param  array<int, int>  $tagIds
     * @return array<int, int>
     */
    public function gameIdsWithAllPrivateTags(array $tagIds): array
    {
        $uniqueTagIds = array_values(array_unique(array_map('intval', $tagIds)));
        if ($uniqueTagIds === []) {
            return [];
        }

        return DB::table('private_tag_games')
            ->join('private_tags', 'private_tags.id', '=', 'private_tag_games.private_tag_id')
            ->where('private_tags.user_id', $this->id)
            ->whereIn('private_tag_games.private_tag_id', $uniqueTagIds)
            ->groupBy('private_tag_games.game_id')
            ->havingRaw('count(distinct private_tag_games.private_tag_id) = ?', [count($uniqueTagIds)])
            ->pluck('private_tag_games.game_id')
            ->all();
    }

    public function ignoredGames(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'user_ignored_games', 'user_id', 'game_id')
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_review_banned' => 'boolean',
        ];
    }

    private function ownedGamesQuery(): Builder
    {
        $accounts = $this->socialAccounts()->where('provider_name', 'itchio')->get();

        return Game::query()->fromItchio()->where(function (Builder $query) use ($accounts): void {
            $query->whereRaw('1 = 0');
            foreach ($accounts as $account) {
                if (! empty($account->itchio_game_ids) && is_array($account->itchio_game_ids)) {
                    $query->orWhereIn('itch_id', $account->itchio_game_ids);

                    continue;
                }

                $host = parse_url($account->provider_data['url'] ?? '', PHP_URL_HOST);
                if (is_string($host) && $host !== '') {
                    $domain = addcslashes(strtolower($host), '%_\\');
                    $query->orWhereRaw("LOWER(url->>'itch_io') LIKE ?", ["https://{$domain}/%"])
                        ->orWhereRaw("LOWER(url->>'itch_io') LIKE ?", ["http://{$domain}/%"]);
                }
            }
        });
    }
}

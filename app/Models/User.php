<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'is_admin',
        'is_review_banned',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'is_review_banned' => 'boolean',
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
        $itchioAccount = $this->socialAccounts()
            ->where('provider_name', 'itchio')
            ->first();

        if (! $itchioAccount) {
            return false;
        }

        if (! empty($itchioAccount->itchio_game_ids) && is_array($itchioAccount->itchio_game_ids)) {
            return in_array($game->itch_id, $itchioAccount->itchio_game_ids, true);
        }

        // Fallback to URL-based check if API data is not available
        // This handles cases where the user logged in before the API integration
        $itchioUrl = $itchioAccount->provider_data['url'] ?? null;

        if (! $itchioUrl) {
            return false;
        }

        $userUrl = parse_url($itchioUrl);
        if (! $userUrl || ! isset($userUrl['host'])) {
            return false;
        }

        $gameUrlString = $game->getUrlForPlatform('itch_io');
        if (! $gameUrlString) {
            return false;
        }

        $gameUrl = parse_url($gameUrlString);
        if (! $gameUrl || ! isset($gameUrl['host'])) {
            return false;
        }

        // Compare the hosts (case-insensitive since domain names are case-insensitive)
        return strtolower($gameUrl['host']) === strtolower($userUrl['host']);
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
        $itchioAccount = $this->socialAccounts()
            ->where('provider_name', 'itchio')
            ->first();

        if (! $itchioAccount) {
            return collect();
        }

        if (! empty($itchioAccount->itchio_game_ids) && is_array($itchioAccount->itchio_game_ids)) {
            return Game::whereIn('itch_id', $itchioAccount->itchio_game_ids)
                ->fromItchio()
                ->where('is_visible', true)
                ->orderBy('name')
                ->get();
        }

        // Fallback to URL-based check if API data is not available
        $itchioUrl = $itchioAccount->provider_data['url'] ?? null;

        if (! $itchioUrl) {
            return collect();
        }

        $userUrl = parse_url($itchioUrl);
        if (! $userUrl || ! isset($userUrl['host'])) {
            return collect();
        }

        $expectedDomain = strtolower($userUrl['host']);

        return Game::where(function ($query) use ($expectedDomain) {
            $query->whereRaw("LOWER(url->>'itch_io') LIKE ?", ["https://{$expectedDomain}/%"])
                ->orWhereRaw("LOWER(url->>'itch_io') LIKE ?", ["http://{$expectedDomain}/%"]);
        })
            ->where('is_visible', true)
            ->orderBy('name')
            ->get();
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

    public function ignoredGames(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'user_ignored_games', 'user_id', 'game_id')
            ->withTimestamps();
    }
}

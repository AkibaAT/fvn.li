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
    ];

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Get the user's itch.io username if they have an itch.io account connected
     */
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

    /**
     * Get the user's itch.io URL if they have an itch.io account connected
     */
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

    /**
     * Check if this user owns a specific game based on their itch.io namespace
     */
    public function ownsGame(Game $game): bool
    {
        $itchioUrl = $this->getItchioUrl();

        if (! $itchioUrl) {
            return false;
        }

        // Extract the domain from the user's itch.io URL
        $userUrl = parse_url($itchioUrl);
        if (! $userUrl || ! isset($userUrl['host'])) {
            return false;
        }

        // Check if the game URL belongs to this user's itch.io namespace
        $gameUrl = parse_url($game->url);
        if (! $gameUrl || ! isset($gameUrl['host'])) {
            return false;
        }

        // Compare the hosts (case-insensitive since domain names are case-insensitive)
        return strtolower($gameUrl['host']) === strtolower($userUrl['host']);
    }

    /**
     * Get all games owned by this user in their itch.io namespace
     */
    public function getOwnedGames()
    {
        $itchioUrl = $this->getItchioUrl();

        if (! $itchioUrl) {
            return collect();
        }

        // Extract the domain from the user's itch.io URL
        $userUrl = parse_url($itchioUrl);
        if (! $userUrl || ! isset($userUrl['host'])) {
            return collect();
        }

        // Use the exact domain from the user's itch.io URL
        $expectedDomain = strtolower($userUrl['host']);

        return Game::where('url', 'LIKE', "https://{$expectedDomain}/%")
            ->orWhere('url', 'LIKE', "http://{$expectedDomain}/%")
            ->where('is_visible', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the user's VN lists.
     */
    public function vnLists(): HasMany
    {
        return $this->hasMany(VnList::class)->latest();
    }

    /**
     * Get the user's game progress records.
     */
    public function gameProgress(): HasMany
    {
        return $this->hasMany(UserGameProgress::class);
    }

    /**
     * Get the user's notification history.
     */
    public function notificationHistory(): HasMany
    {
        return $this->hasMany(NotificationHistory::class);
    }

    /**
     * Get the user's notification preferences.
     */
    public function notificationPreferences(): HasOne
    {
        return $this->hasOne(UserNotificationPreferences::class);
    }

    /**
     * Get the user's push subscriptions.
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Get the user's addition requests.
     */
    public function additionRequests(): BelongsToMany
    {
        return $this->belongsToMany(AdditionRequest::class, 'addition_request_users')
            ->withTimestamps()
            ->orderBy('addition_request_users.created_at', 'desc');
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
}

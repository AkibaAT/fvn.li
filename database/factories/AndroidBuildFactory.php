<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AndroidBuild;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AndroidBuild>
 */
class AndroidBuildFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_id' => Game::factory(),
            'game_version_id' => GameVersion::factory(),
            'build_id' => (string) Str::uuid(),
            'status' => 'pending',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'build_path' => 'public/android_builds/game/version/fvn.apk',
            'completed_at' => now(),
        ]);
    }
}

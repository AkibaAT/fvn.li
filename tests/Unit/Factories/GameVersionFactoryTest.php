<?php

declare(strict_types=1);

use App\Models\GameVersion;

test('default version names stay unique across batches and separate factory calls', function () {
    $versions = GameVersion::factory()->count(1001)->make(['game_id' => 1])->pluck('version');
    $versions->push(GameVersion::factory()->make(['game_id' => 1])->version);

    expect($versions->unique())->toHaveCount(1002);
});

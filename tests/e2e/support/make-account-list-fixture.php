<?php

declare(strict_types=1);

ob_start();
require __DIR__ . '/make-consistency-fixture.php';
$fixture = json_decode(ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);

$user->notificationPreferences()->updateOrCreate([], [
    'browser_notifications_enabled' => false,
    'discord_notifications_enabled' => false,
    'notification_digest' => 'asap',
]);
if (($argv[1] ?? '') === 'last-provider') {
    $user->socialAccounts()->where('provider_name', '!=', 'discord')->get()->each->delete();
}
if (($argv[1] ?? '') === 'account-deletion') {
    $user->forceFill(['is_admin' => false])->saveQuietly();
}

echo json_encode([...$fixture, 'gameId' => $game->id, 'extraGameId' => $ratedGame->id, 'extraGameName' => $ratedGame->name], JSON_THROW_ON_ERROR);

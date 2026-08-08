<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Discord\DiscordUserInstallService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives Discord's application webhook events so authorization changes made
 * outside the dashboard are reflected here too.
 */
class DiscordWebhookEventsController extends Controller
{
    private const PING = 0;

    private const EVENT = 1;

    private const USER_INSTALL = 1;

    public function __invoke(Request $request, DiscordUserInstallService $installs): Response
    {
        if (! $this->signatureIsValid($request)) {
            return response('invalid signature', 401);
        }

        $type = (int) $request->input('type');

        if ($type === self::PING) {
            return response('', 204);
        }

        if ($type !== self::EVENT) {
            return response('', 204);
        }

        $event = (string) $request->input('event.type');
        $data = (array) $request->input('event.data', []);
        $discordUserId = (string) ($data['user']['id'] ?? '');

        if ($discordUserId === '') {
            return response('', 204);
        }

        $user = $installs->userForDiscordId($discordUserId);

        if (! $user) {
            return response('', 204);
        }

        match (true) {
            $event === 'APPLICATION_AUTHORIZED'
                && (int) ($data['integration_type'] ?? -1) === self::USER_INSTALL => $installs->recordInstalled($user),
            $event === 'APPLICATION_DEAUTHORIZED' => $installs->recordUninstalled($user),
            default => null,
        };

        return response('', 204);
    }

    private function signatureIsValid(Request $request): bool
    {
        $publicKey = (string) config('services.discord.public_key');
        $signature = (string) $request->header('X-Signature-Ed25519', '');
        $timestamp = (string) $request->header('X-Signature-Timestamp', '');

        if ($publicKey === '' || $signature === '' || $timestamp === '') {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached(
                sodium_hex2bin($signature),
                $timestamp.$request->getContent(),
                sodium_hex2bin($publicKey),
            );
        } catch (\SodiumException $exception) {
            Log::warning('Discord webhook signature could not be parsed', ['message' => $exception->getMessage()]);

            return false;
        }
    }
}

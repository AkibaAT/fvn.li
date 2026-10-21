<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\UserApiAbility;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public const MAX_ACTIVE_TOKENS = 20;

    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->orderByDesc('created_at')->get()
            ->filter(fn ($token) => UserApiAbility::isUserApiToken($token))
            ->map(fn ($token) => $this->metadata($token))->values();

        return response()->json(['data' => $tokens, 'abilities' => UserApiAbility::values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::enum(UserApiAbility::class)],
        ]);
        $active = $request->user()->tokens()->where('expires_at', '>', now())->get(['abilities'])
            ->filter(fn ($token) => UserApiAbility::isUserApiToken($token))->count();
        if ($active >= self::MAX_ACTIVE_TOKENS) {
            throw ValidationException::withMessages([
                'token' => 'You can have at most ' . self::MAX_ACTIVE_TOKENS . ' active API tokens. Revoke one before creating another.',
            ]);
        }
        $token = $request->user()->createToken($input['name'],
            [UserApiAbility::TOKEN_MARKER, ...$input['abilities']], now()->addDays(90));

        return response()->json([
            'data' => $this->metadata($token->accessToken),
            'token' => $token->plainTextToken,
        ], 201)->header('Cache-Control', 'no-store');
    }

    public function extend(Request $request, int $token): JsonResponse
    {
        $owned = $this->owned($request, $token);
        $next = $this->extendedExpiry($owned);
        if ($next === null) {
            throw ValidationException::withMessages(['token' => 'This token cannot be extended.']);
        }
        $owned->update(['expires_at' => $next]);

        return response()->json(['data' => $this->metadata($owned->fresh())]);
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        $this->owned($request, $token)->delete();

        return response()->json(status: 204);
    }

    private function owned(Request $request, int $id): PersonalAccessToken
    {
        $token = $request->user()->tokens()->findOrFail($id);
        abort_unless(UserApiAbility::isUserApiToken($token), 404);

        return $token;
    }

    private function extendedExpiry(PersonalAccessToken $token): ?CarbonInterface
    {
        if ($token->expires_at->isPast()) {
            return null;
        }
        $next = now()->addDays(90)->min($token->created_at->copy()->addDays(365));

        return $next->gt($token->expires_at) ? $next : null;
    }

    private function metadata(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => array_values(array_diff($token->abilities, [UserApiAbility::TOKEN_MARKER])),
            'created_at' => $token->created_at->toIso8601String(),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at->toIso8601String(),
            'is_extendable' => $this->extendedExpiry($token) !== null,
        ];
    }
}

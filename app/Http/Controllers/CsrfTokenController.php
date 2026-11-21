<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class CsrfTokenController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }
}

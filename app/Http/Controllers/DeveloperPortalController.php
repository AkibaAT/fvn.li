<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Seo\MetaTags;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DeveloperPortalController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('developers/index', [
            'metaTags' => new MetaTags(title: 'Developer API', description: 'Build with the fvn.li user API.', noindex: true)->toArray(),
        ]);
    }

    public function swagger(Request $request): HttpResponse
    {
        return Inertia::render('developers/swagger', [
            'metaTags' => new MetaTags(title: 'API Explorer', noindex: true)->toArray(),
        ])->toResponse($request)->header('Cache-Control', 'no-store');
    }

    public function openapi(): BinaryFileResponse
    {
        return response()->file(resource_path('openapi/user-api.yaml'), ['Content-Type' => 'application/yaml']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function loginResponse(): Response
    {
        return Inertia::render('auth/login', [
            'metaTags' => [
                'title' => 'Log in',
                'description' => 'Log in to your FVN.li account to track your visual novel progress, create reading lists, and connect with the community.',
                'structuredData' => [
                    '@type' => 'WebPage',
                    'name' => 'Log in',
                    'description' => 'Log in to your FVN.li account to track your visual novel progress',
                    'url' => route('login'),
                ],
            ],
        ]);
    }
}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ request()->cookie('appearance') === 'dark' ? 'dark' : '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>@yield('title') - FVN.li</title>
        <style>
            :root {
                color-scheme: light;
                --error-background: #f3f4f6;
                --error-surface: #ffffff;
                --error-text: #111827;
                --error-muted: #4b5563;
                --error-border: #e5e7eb;
                --error-link: #2563eb;
                --error-link-hover: #1d4ed8;
                --error-shadow: rgba(17, 24, 39, 0.08);
            }

            .dark {
                color-scheme: dark;
                --error-background: #111827;
                --error-surface: #1f2937;
                --error-text: #f9fafb;
                --error-muted: #d1d5db;
                --error-border: #4b5563;
                --error-link: #60a5fa;
                --error-link-hover: #93c5fd;
                --error-shadow: rgba(0, 0, 0, 0.3);
            }

            * {
                box-sizing: border-box;
            }

            html,
            body {
                min-height: 100%;
                margin: 0;
            }

            body {
                display: grid;
                min-height: 100vh;
                place-items: center;
                padding: 1.5rem;
                background: var(--error-background);
                color: var(--error-text);
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            main {
                width: min(100%, 32rem);
                padding: clamp(2rem, 7vw, 4rem);
                border: 1px solid var(--error-border);
                border-radius: 1rem;
                background: var(--error-surface);
                box-shadow: 0 1.25rem 3rem var(--error-shadow);
                text-align: center;
            }

            .code {
                margin: 0 0 0.75rem;
                color: var(--error-link);
                font-size: clamp(3rem, 14vw, 5rem);
                font-weight: 800;
                line-height: 1;
                letter-spacing: -0.06em;
            }

            h1 {
                margin: 0;
                font-size: clamp(1.5rem, 5vw, 2rem);
                line-height: 1.2;
            }

            p {
                margin: 1rem 0 0;
                color: var(--error-muted);
                line-height: 1.6;
            }

            a {
                display: inline-flex;
                margin-top: 1.75rem;
                color: var(--error-link);
                font-weight: 700;
                text-underline-offset: 0.2em;
            }

            a:hover {
                color: var(--error-link-hover);
            }

            a:focus-visible {
                border-radius: 0.25rem;
                outline: 3px solid var(--error-link);
                outline-offset: 0.25rem;
            }
        </style>
        @include('partials.appearance-script')
    </head>
    <body>
        <main>
            <p class="code">@yield('code')</p>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <a href="{{ route('home') }}">Return to home</a>
        </main>
    </body>
</html>

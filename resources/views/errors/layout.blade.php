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
                --error-background: #edf0f4;
                --error-surface: #ffffff;
                --error-text: #161c26;
                --error-muted: #5a6578;
                --error-border: #d5dbe3;
                --error-accent: #b07a14;
            }

            .dark {
                color-scheme: dark;
                --error-background: #171b24;
                --error-surface: #222836;
                --error-text: #eef1f6;
                --error-muted: #a6b0c2;
                --error-border: #3a4356;
                --error-accent: #f2bb4a;
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
                border-radius: 0.5rem;
                background: var(--error-surface);
                text-align: center;
            }

            .code {
                margin: 0 0 0.75rem;
                color: var(--error-accent);
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
                color: var(--error-accent);
                font-weight: 700;
                text-underline-offset: 0.2em;
            }

            a:hover {
                opacity: 0.85;
            }

            a:focus-visible {
                border-radius: 0.25rem;
                outline: 2px solid var(--error-accent);
                outline-offset: 2px;
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

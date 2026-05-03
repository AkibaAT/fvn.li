# Development Guide

This guide covers day-to-day development for FVN.li's Laravel 13 + Svelte 5 + Inertia frontend.

## Prerequisites

- Docker and DDEV
- Composer through DDEV
- Bun through DDEV

The web container currently runs PHP 8.5, and dependencies are managed from the repo lockfiles.

## Initial Setup

```bash
ddev start
ddev composer install
ddev bun install
cp .env.example .env
ddev artisan key:generate
ddev artisan migrate
```

Start the Vite dev server:

```bash
ddev bun dev
```

Open the app at `https://fvn-li.ddev.site`.

## Frontend Stack

- Svelte page components live in `resources/js/pages/**/*.svelte`.
- Reusable Svelte components live in `resources/js/components/`.
- Inertia layouts live in `resources/js/layouts/`.
- Reusable state helpers live in `resources/js/hooks/*.svelte.ts` or plain TypeScript modules.
- Browser JSON endpoints use `/browser-api` and `browser-api.*` route names.
- Stateless external APIs remain under `/api`.

## Creating Components

Create Svelte components in `resources/js/components/`:

```svelte
<script lang="ts">
    let {
        title,
        description = '',
    }: {
        title: string;
        description?: string;
    } = $props();
</script>

<section class="space-y-2">
    <h2 class="text-lg font-semibold">{title}</h2>
    {#if description}
        <p class="text-sm text-gray-600 dark:text-gray-400">{description}</p>
    {/if}
</section>
```

Create Inertia pages in `resources/js/pages/` and return them from Laravel with `Inertia::render()`.

## Browser API Calls

Prefer Ziggy route names over hard-coded paths when the route is named:

```ts
import http from '@/utils/http';

await http.put(route('browser-api.games.content.view-mode', { game: gameId }), {
    show_custom: true,
});
```

For file uploads, use `authenticatedFetch` helpers where the component needs native `FormData` handling or progress state.

## Code Quality

```bash
ddev bun types
ddev bun lint
ddev bun format:check
ddev composer lint
```

`ddev bun types` runs `svelte-check`; it is the primary frontend type gate.

## Testing

Run PHP tests through DDEV:

```bash
ddev composer test
```

Run Svelte/TypeScript unit tests:

```bash
ddev bun test:js
```

Run Playwright through the DDEV sidecar:

```bash
ddev playwright test
ddev playwright test tests/e2e/specs/accessibility.spec.ts --grep @accessibility
```

Coverage:

```bash
ddev composer test:coverage:clover
ddev composer test:coverage:audit
```

Do not run PHP feature tests concurrently against the shared `db_test` database unless each process has isolated database state.

## Build

Client build:

```bash
ddev bun build
```

Client + SSR build:

```bash
ddev bun build:ssr
```

The SSR entry point is `resources/js/ssr.ts`, and the browser entry point is `resources/js/app.ts`.

## Debugging

- Use browser DevTools for Svelte component output and network requests.
- Inertia page props are visible in the page payload and browser globals during development.
- Source maps are enabled in development.
- Laravel logs are in `storage/logs/laravel.log`.
- Browser API requests should hit `/browser-api`; do not add new endpoints under old framework-specific prefixes.

## Frontend Best Practices

- Use Svelte runes for component-local state.
- Keep shared reusable state in `.svelte.ts` helpers when it needs Svelte reactivity.
- Keep plain deterministic logic in `.ts` utilities so it can be covered with Vitest.
- Use `resources/js/utils/http.ts` for Axios calls that need CSRF retry behavior.
- Prefer accessible native elements and shared dialog helpers.
- Keep route names and docs aligned with the current Svelte/browser API naming.

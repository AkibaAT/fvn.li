# Frontend Architecture

FVN.li uses a Svelte 5 frontend with Inertia.js. Laravel owns routing, auth, validation, and page props; Svelte owns the browser experience, interactive components, and local UI state.

## Technology Stack

### Core Framework
- **Svelte 5.55**: Component framework using runes and `.svelte` single-file components.
- **Inertia.js 2.3**: Connects Laravel controllers to Svelte pages without a separate SPA API layer.
- **TypeScript**: Shared types for page props, route-map data, filters, and browser utilities.
- **Vite 8**: Development server, HMR, production builds, and SSR bundle generation.

### UI And Styling
- **Tailwind CSS 4.2**: Utility-first styling and theme tokens.
- **TinyMCE 8**: Rich text editing for custom game descriptions.
- **Chart.js + svelte5-chartjs**: Analytics and dashboard charts.
- **@xyflow/svelte**: Route-map graph rendering.
- **@dnd-kit-svelte/svelte**: Sortable lists and drag interactions.
- **Ziggy**: Laravel route helper exposed to TypeScript and Svelte.

### Browser JSON Endpoints

Session-backed JSON routes live in `routes/browser-api.php` and are mounted under `/browser-api`.

Use these for browser UI actions that need web middleware, session auth, CSRF, or Laravel validation. Stateless external integrations remain in `routes/api.php`.

Route names use the `browser-api.*` prefix, for example:

```ts
route('browser-api.my-games.screenshots.upload', { game: gameSlug })
route('browser-api.games.content.view-mode', { game: gameId })
```

## Project Structure

```text
resources/js/
├── app.ts                         # Browser Inertia entry point
├── ssr.ts                         # Svelte SSR entry point
├── components/                    # Reusable Svelte components
│   ├── charts/                    # Chart wrappers
│   ├── dashboard/                 # Dashboard widgets
│   ├── editor/                    # Custom game content editor
│   ├── games/                     # Game page and gallery components
│   └── ui/                        # Small reusable UI primitives
├── constants/                     # Shared frontend constants
├── hooks/                         # Svelte/TypeScript reusable state helpers
├── layouts/                       # Inertia layouts
├── pages/                         # Inertia page components
├── types/                         # TypeScript declarations
└── utils/                         # HTTP, CSRF, dialogs, formatting, toast helpers
```

## Inertia Setup

`resources/js/app.ts` resolves Svelte pages from `resources/js/pages/**/*.svelte`, applies `PersistentLayout.svelte` by default, and hydrates server-rendered markup when SSR is present.

`resources/js/ssr.ts` uses `@inertiajs/svelte/server`, `svelte/server`, and Ziggy globals so server-side rendering can resolve Laravel routes the same way the browser does.

## State And Data Flow

- Laravel controllers return Inertia pages with typed props.
- Shared data carries global user/session state.
- Svelte runes and `.svelte.ts` helper modules manage local state.
- URL query parameters preserve search, filters, and pagination.
- `resources/js/utils/http.ts` centralizes Axios defaults and CSRF retry behavior.
- `authenticatedFetch` helpers are used where fetch is more convenient for file uploads.

## Forms And Uploads

Forms use a mix of Inertia form submissions, Axios, and fetch depending on the workflow. Browser API endpoints return JSON validation errors for interactive components such as screenshots, thumbnails, custom content, bug reports, reviews, and notification preferences.

Image upload controls should show upload progress or pending state. The screenshot and thumbnail editors update local Svelte state from the JSON response instead of requiring a full page reload.

## Testing

- **PHPUnit/Pest** covers Laravel controllers, services, commands, models, and browser API behavior.
- **Vitest** covers focused Svelte/TypeScript logic where browser rendering is not needed.
- **Playwright** covers end-to-end and accessibility flows through the official Playwright sidecar configured in DDEV.

Use:

```bash
ddev composer test:coverage:clover
ddev composer test:coverage:audit
ddev bun test:js
ddev playwright test
```

Do not run PHP feature tests concurrently against the shared `db_test` database unless each process has isolated database state.

## Historical Notes

The frontend previously used framework-specific naming in docs and route names. Current code should refer to Svelte components and browser API routes. Avoid adding new outdated browser endpoint names, files, comments, or documentation.

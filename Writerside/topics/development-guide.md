# Development Guide

This guide covers common development tasks and workflows for working with FVN.li's React + Inertia.js frontend.

## Getting Started

### Prerequisites

Ensure you have the following installed:
- Docker and DDEV
- Node.js 22+ (managed by DDEV)
- Bun (installed via DDEV)

### Initial Setup

1. Start DDEV environment:
   ```bash
   ddev start
   ```

2. Install dependencies:
   ```bash
   ddev composer install
   ddev bun install
   ```

3. Set up environment:
   ```bash
   cp .env.example .env
   ddev artisan key:generate
   ddev artisan migrate
   ```

4. Start development server:
   ```bash
   ddev bun dev
   ```

5. Access the application at `https://fvn-li.ddev.site`

## Development Workflow

### Running the Dev Server

The Vite development server provides hot module replacement (HMR) for instant feedback:

```bash
# Start Vite dev server
ddev bun dev
```

Features:
- **Hot Module Replacement**: Changes appear instantly without page reload
- **Fast Refresh**: React state is preserved during updates
- **Error Overlay**: Build errors appear in the browser
- **Source Maps**: Debug with original TypeScript source

### Code Quality Tools

#### Type Checking

Run TypeScript compiler to check for type errors:

```bash
ddev bun types
```

This runs `tsc --noEmit` to check types without generating output files.

#### Linting

Check and fix code style issues:

```bash
# Check for issues
ddev bun lint

# Auto-fix issues
ddev bun lint --fix
```

ESLint configuration includes:
- React best practices
- React Hooks rules
- TypeScript-specific rules
- Accessibility checks

#### Formatting

Format code with Prettier:

```bash
# Format all files
ddev bun format

# Check formatting without changes
ddev bun format:check
```

Prettier is configured to:
- Sort imports automatically
- Format Tailwind classes
- Maintain consistent code style

### Building for Production

#### Client-Side Only

```bash
ddev bun build
```

This creates optimized production assets in `public/build/`.

#### With SSR Support

```bash
ddev bun build:ssr
```

This builds both client and server bundles:
- `public/build/` - Client-side assets
- `bootstrap/ssr/ssr.mjs` - Server-side rendering bundle

## Creating Components

### Basic Component

Create a new component in `resources/js/components/`:

```typescript
// resources/js/components/MyComponent.tsx
import React from 'react';

interface MyComponentProps {
    title: string;
    description?: string;
}

export default function MyComponent({ title, description }: MyComponentProps) {
    return (
        <div className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <h3 className="text-lg font-semibold">{title}</h3>
            {description && (
                <p className="mt-2 text-gray-600 dark:text-gray-400">
                    {description}
                </p>
            )}
        </div>
    );
}
```

### Page Component

Create a new page in `resources/js/pages/`:

```typescript
// resources/js/pages/example/index.tsx
import React from 'react';
import { Head } from '@inertiajs/react';

interface ExamplePageProps {
    items: Array<{ id: number; name: string }>;
}

export default function ExamplePage({ items }: ExamplePageProps) {
    return (
        <>
            <Head title="Example Page" />
            
            <div className="container mx-auto px-4 py-8">
                <h1 className="text-3xl font-bold">Example Page</h1>
                
                <div className="mt-6 grid gap-4">
                    {items.map((item) => (
                        <div key={item.id} className="rounded-lg border p-4">
                            {item.name}
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}
```

### Custom Hook

Create reusable logic in `resources/js/hooks/`:

```typescript
// resources/js/hooks/useLocalStorage.ts
import { useState, useEffect } from 'react';

export function useLocalStorage<T>(key: string, initialValue: T) {
    const [value, setValue] = useState<T>(() => {
        try {
            const item = window.localStorage.getItem(key);
            return item ? JSON.parse(item) : initialValue;
        } catch {
            return initialValue;
        }
    });

    useEffect(() => {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {
            console.error('Error saving to localStorage:', error);
        }
    }, [key, value]);

    return [value, setValue] as const;
}
```

## Working with Inertia

### Navigation

Use Inertia's `Link` component for navigation:

```typescript
import { Link } from '@inertiajs/react';

<Link 
    href={route('games.show', { game: gameId })}
    className="text-blue-600 hover:underline"
>
    View Game
</Link>
```

### Forms

Use Inertia's form helpers for form handling:

```typescript
import { useForm } from '@inertiajs/react';

export default function CreateForm() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('items.store'));
    };

    return (
        <form onSubmit={handleSubmit}>
            <div>
                <label htmlFor="name">Name</label>
                <input
                    id="name"
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                />
                {errors.name && <div className="text-red-600">{errors.name}</div>}
            </div>

            <button type="submit" disabled={processing}>
                {processing ? 'Saving...' : 'Save'}
            </button>
        </form>
    );
}
```

### Shared Data

Access shared data from the page props:

```typescript
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

export default function MyComponent() {
    const { auth, flash } = usePage<PageProps>().props;
    
    return (
        <div>
            {auth.user && <p>Welcome, {auth.user.name}!</p>}
            {flash.message && <div className="alert">{flash.message}</div>}
        </div>
    );
}
```

## Styling with Tailwind

### Using Tailwind Classes

```typescript
<div className="flex items-center justify-between rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
    <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
        Title
    </h2>
    <button className="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
        Action
    </button>
</div>
```

### Dark Mode

Use Tailwind's dark mode classes:

```typescript
<div className="bg-white text-gray-900 dark:bg-gray-900 dark:text-white">
    Content adapts to dark mode
</div>
```

The application automatically detects system preference and allows user override.

### Responsive Design

Use Tailwind's responsive prefixes:

```typescript
<div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
    {/* Responsive grid */}
</div>
```

## Testing

### E2E Tests with Playwright

Create tests in `tests/e2e/specs/`:

```typescript
// tests/e2e/specs/example.spec.ts
import { test, expect } from '@playwright/test';

test('homepage loads correctly', async ({ page }) => {
    await page.goto('/');
    
    await expect(page).toHaveTitle(/FVN.li/);
    await expect(page.locator('h1')).toBeVisible();
});

test('search functionality works', async ({ page }) => {
    await page.goto('/games');
    
    await page.fill('[placeholder="Search games..."]', 'visual novel');
    await page.keyboard.press('Enter');
    
    await expect(page).toHaveURL(/search=visual\+novel/);
});
```

Run tests:

```bash
# Run all tests
ddev bun test:e2e

# Run in UI mode
ddev bun test:e2e:ui

# Run specific test file
ddev playwright test tests/e2e/specs/example.spec.ts
```

### Accessibility Testing

```bash
# Run accessibility tests
ddev bun test:a11y

# View accessibility report
ddev bun test:a11y:report
```

## Common Tasks

### Adding a New Route

1. Create the page component:
   ```typescript
   // resources/js/pages/my-feature/index.tsx
   export default function MyFeature() {
       return <div>My Feature</div>;
   }
   ```

2. Add the route in Laravel:
   ```php
   // routes/web.php
   Route::get('/my-feature', [MyFeatureController::class, 'index'])
       ->name('my-feature.index');
   ```

3. Create the controller:
   ```php
   // app/Http/Controllers/MyFeatureController.php
   public function index()
   {
       return inertia('my-feature/index', [
           'data' => MyModel::all(),
       ]);
   }
   ```

### Adding TypeScript Types

Define types in `resources/js/types/index.ts`:

```typescript
export interface Game {
    id: number;
    title: string;
    description: string;
    rating: number;
    created_at: string;
}

export interface PageProps {
    auth: {
        user: User | null;
    };
    flash: {
        message?: string;
        error?: string;
    };
}
```

### Using Environment Variables

Access Vite environment variables:

```typescript
const appName = import.meta.env.VITE_APP_NAME;
const apiUrl = import.meta.env.VITE_API_URL;
```

Define in `.env`:
```
VITE_APP_NAME="FVN.li"
VITE_API_URL="https://api.fvn.li"
```

## Debugging

### Browser DevTools

- **React DevTools**: Install the React DevTools browser extension
- **Inertia DevTools**: Available in development mode
- **Vue DevTools**: Can also inspect Inertia state

### Console Logging

```typescript
// Development only logging
if (import.meta.env.DEV) {
    console.log('Debug info:', data);
}
```

### Source Maps

Source maps are enabled in development, allowing you to debug TypeScript source directly in the browser.

## Performance Optimization

### Code Splitting

Lazy load heavy components:

```typescript
import { lazy, Suspense } from 'react';

const HeavyComponent = lazy(() => import('./HeavyComponent'));

export default function Page() {
    return (
        <Suspense fallback={<div>Loading...</div>}>
            <HeavyComponent />
        </Suspense>
    );
}
```

### Memoization

Use React.memo for expensive components:

```typescript
import { memo } from 'react';

const ExpensiveComponent = memo(function ExpensiveComponent({ data }) {
    // Expensive rendering logic
    return <div>{/* ... */}</div>;
});
```

### Image Optimization

Use appropriate image formats and sizes:

```typescript
<img
    src={game.thumbnail}
    srcSet={`${game.thumbnail_2x} 2x`}
    alt={game.title}
    loading="lazy"
    className="h-48 w-full object-cover"
/>
```

## Troubleshooting

### HMR Not Working

1. Check that Vite dev server is running
2. Verify DDEV configuration in `vite.config.ts`
3. Clear browser cache
4. Restart Vite dev server

### Type Errors

1. Run `ddev bun types` to see all type errors
2. Check that types are properly imported
3. Ensure `tsconfig.json` paths are correct
4. Restart TypeScript server in your IDE

### Build Failures

1. Check for TypeScript errors: `ddev bun types`
2. Check for linting errors: `ddev bun lint`
3. Clear build cache: `rm -rf public/build`
4. Reinstall dependencies: `ddev bun install`


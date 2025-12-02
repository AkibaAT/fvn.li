# Frontend Architecture

FVN.li uses a modern React-based frontend architecture with Inertia.js, providing a seamless single-page application experience while maintaining the simplicity of server-side routing.

## Technology Stack

### Core Framework
- **React 19**: Latest version with improved performance and concurrent features
- **Inertia.js 2.x**: Bridges Laravel backend with React frontend without building an API
- **TypeScript**: Full type safety with strict mode enabled
- **Vite 7**: Lightning-fast build tool with HMR (Hot Module Replacement)

### UI & Styling
- **Tailwind CSS 4**: Utility-first CSS framework with custom design system
- **Headless UI**: Unstyled, accessible UI components
- **Heroicons**: SVG icon library
- **Flag Icons**: Country flag icons for language support

### State & Data Management
- **Inertia Shared Data**: Global state (user, flash messages, filters)
- **React Hooks**: Component-local state management
- **URL Parameters**: Filter and search state persistence
- **Ziggy**: Laravel route helper for type-safe routing in TypeScript

### Forms & Validation
- **Inertia Form Helpers**: Built-in form state management
- **Client-side Validation**: Real-time validation feedback
- **CSRF Protection**: Automatic token handling
- **File Uploads**: Progress tracking and error handling

### Rich Content
- **TinyMCE 8**: WYSIWYG editor for game descriptions and content
- **Chart.js 4**: Data visualization for analytics
- **react-chartjs-2**: React wrapper for Chart.js
- **dnd-kit**: Drag and drop for sortable lists

## Project Structure

```
resources/js/
├── app.tsx                    # Client-side entry point
├── ssr.tsx                    # Server-side rendering entry point
├── components/                # Reusable components
│   ├── ui/                   # Base UI components
│   │   ├── Badge.tsx
│   │   ├── Button.tsx
│   │   ├── Card.tsx
│   │   ├── Modal.tsx
│   │   └── Tooltip.tsx
│   ├── layout/               # Layout components
│   │   ├── Header.tsx
│   │   ├── Navigation.tsx
│   │   ├── SearchBar.tsx
│   │   ├── UserMenu.tsx
│   │   └── FlashMessages.tsx
│   ├── games/                # Game-specific components
│   │   ├── GamesGrid.tsx
│   │   ├── GameCard.tsx
│   │   ├── ActiveFilterChips.tsx
│   │   └── SortControls.tsx
│   ├── charts/               # Chart components
│   │   └── chart.tsx
│   ├── editor/               # Rich text editor
│   │   ├── TinyMCEEditor.tsx
│   │   └── EditableGameContent.tsx
│   └── form-elements.tsx     # Form components
├── pages/                    # Inertia page components
│   ├── games/
│   │   ├── index.tsx        # Game listing
│   │   └── show.tsx         # Game detail
│   ├── dashboard/
│   │   ├── index.tsx        # User dashboard
│   │   └── show.tsx         # Game dashboard
│   ├── lists/               # Custom lists
│   ├── auth/                # Authentication
│   └── home.tsx             # Homepage
├── layouts/                  # Page layouts
│   ├── PersistentLayout.tsx # Main layout (persistent header/footer)
│   └── SimpleLayout.tsx     # Minimal layout (auth pages)
├── hooks/                    # Custom React hooks
│   ├── useSearch.ts
│   ├── useGameFilters.ts
│   ├── useAccessibility.ts
│   ├── useStableRoutes.ts
│   └── use-appearance.ts
├── types/                    # TypeScript definitions
│   └── index.ts
└── utils/                    # Utility functions
    └── accessibility.ts
```

## Key Architectural Patterns

### Persistent Layouts

The application uses a persistent layout system where the header, navigation, and footer remain mounted across page transitions:

```typescript
// app.tsx
createInertiaApp({
    resolve: (name) => {
        return resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ).then((page) => {
            // Force all pages to use persistent layout
            page.default.layout = (pageContent) => 
                <PersistentLayout>{pageContent}</PersistentLayout>;
            return page;
        });
    },
});
```

Benefits:
- Smooth page transitions without re-mounting the header/footer
- Maintains search state and navigation state
- Reduces layout shift and improves perceived performance
- Better accessibility with focus management

### Server-Side Rendering (SSR)

Inertia SSR is enabled for improved SEO and initial page load performance:

```typescript
// ssr.tsx
createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        // ... configuration
    })
);
```

The SSR process:
1. Server renders React components to HTML
2. HTML is sent to the client with initial props
3. React hydrates the HTML on the client side
4. Subsequent navigation uses client-side routing

### Type-Safe Routing

Ziggy provides Laravel routes in TypeScript with full type safety:

```typescript
import { route } from 'ziggy-js';

// Type-safe route generation
const url = route('games.show', { game: gameId });

// With query parameters
const searchUrl = route('games.index', { 
    search: 'visual novel',
    sort: 'rating'
});
```

### Component Composition

UI components follow composition patterns for flexibility:

```typescript
// Card component with composition
<Card>
    <CardHeader>
        <CardTitle>Game Title</CardTitle>
        <CardDescription>Description</CardDescription>
    </CardHeader>
    <CardContent>
        {/* Content */}
    </CardContent>
    <CardFooter>
        {/* Actions */}
    </CardFooter>
</Card>
```

### Custom Hooks

Reusable logic is extracted into custom hooks:

```typescript
// useSearch.ts - Search functionality
export function useSearch() {
    const [searchTerm, setSearchTerm] = useState('');
    const debouncedSearch = useDebouncedValue(searchTerm, 300);
    
    useEffect(() => {
        if (debouncedSearch) {
            router.get(route('games.index', { search: debouncedSearch }));
        }
    }, [debouncedSearch]);
    
    return { searchTerm, setSearchTerm };
}

// useGameFilters.ts - Filter management
export function useGameFilters() {
    const { data } = usePage<PageProps>();
    const filters = data.filters;
    
    const applyFilters = (newFilters: Filters) => {
        router.get(route('games.index', newFilters), {
            preserveState: true,
            preserveScroll: true,
        });
    };
    
    return { filters, applyFilters };
}
```

### Accessibility

The application prioritizes accessibility:

- **Semantic HTML**: Proper use of semantic elements
- **ARIA Labels**: Descriptive labels for screen readers
- **Keyboard Navigation**: Full keyboard support
- **Focus Management**: Proper focus handling on navigation
- **Color Contrast**: WCAG AA compliant color schemes
- **Responsive Design**: Mobile-first approach

```typescript
// useAccessibility.ts
export function useAccessibility() {
    useEffect(() => {
        // Announce page changes to screen readers
        const title = document.title;
        announceToScreenReader(`Navigated to ${title}`);
        
        // Manage focus after navigation
        const mainContent = document.querySelector('main');
        mainContent?.focus();
    }, []);
}
```

## Build Process

### Development

```bash
ddev bun dev
```

Vite development server features:
- **Hot Module Replacement (HMR)**: Instant updates without page reload
- **Fast Refresh**: Preserves component state during updates
- **WebSocket over SSL**: Secure HMR connection in DDEV
- **Source Maps**: Easy debugging with original TypeScript source

### Production Build

```bash
ddev bun build:ssr
```

Build optimizations:
- **Code Splitting**: Automatic route-based code splitting
- **Tree Shaking**: Removes unused code
- **Minification**: Compresses JavaScript and CSS
- **Asset Optimization**: Optimizes images and fonts
- **SSR Bundle**: Generates server-side rendering bundle
- **Source Maps**: Production source maps for error tracking

## Migration from Livewire

The application was migrated from Laravel Livewire to React + Inertia.js for:

### Benefits Gained
- **Better Performance**: Virtual DOM and optimized re-renders
- **Type Safety**: Full TypeScript coverage
- **Developer Experience**: Better tooling, HMR, and debugging
- **Component Ecosystem**: Access to React ecosystem
- **SSR Support**: Improved SEO and initial load performance
- **Testing**: Better E2E testing with Playwright

### Key Changes
- **Removed**: Livewire components and Alpine.js
- **Added**: React components with TypeScript
- **Replaced**: Blade templates with React JSX/TSX
- **Updated**: Build system from Laravel Mix to Vite
- **Enhanced**: Form handling with Inertia form helpers
- **Improved**: State management with React hooks

## Best Practices

### Component Organization
- Keep components small and focused
- Use composition over inheritance
- Extract reusable logic into custom hooks
- Maintain consistent file naming (PascalCase for components)

### Type Safety
- Define interfaces for all props
- Use TypeScript strict mode
- Avoid `any` type - use `unknown` when necessary
- Leverage type inference where possible

### Performance
- Use React.memo for expensive components
- Implement proper key props in lists
- Lazy load heavy components
- Optimize images and assets

### Accessibility Best Practices
- Use semantic HTML elements
- Provide ARIA labels where needed
- Ensure keyboard navigation works
- Test with screen readers
- Maintain color contrast ratios


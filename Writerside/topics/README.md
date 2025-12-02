# FVN.li - Visual Novel Analytics and Tracking

FVN.li is a web application that tracks, analyzes, and provides insights into games published on itch.io. It collects
data about games, their versions, ratings, and dialogue content, making it easier for users to discover and evaluate
games on the platform. The project is deployed and accessible at [FVN.li](https://fvn.li).

## Features

- **Game Tracking**: Monitor games published on itch.io, including metadata, versions, and ratings
- **Dialogue Browser**: Explore game dialogue content across different versions and languages
- **Rating System**: View and analyze game ratings from the community
- **Language Support**: Track supported languages for games and analyze translation coverage
- **Character Statistics**: View character statistics and dialogue distribution
- **Discord Bot Integration**: Get notified about game updates via Discord

## Tech Stack

### Backend
- **Framework**: Laravel 12 with PHP 8.4
- **Database**: PostgreSQL 17
- **Caching**: Redis for session management and application caching
- **Search**: Meilisearch for full-text search and filtering
- **Queue**: Redis-backed queue system for background jobs
- **API**: RESTful API endpoints for Discord bot integration

### Frontend
- **Framework**: React 19 with TypeScript
- **Routing**: Inertia.js 2.x for seamless SPA experience
- **Build Tool**: Vite 7 with Hot Module Replacement (HMR)
- **Styling**: Tailwind CSS 4 with custom design system
- **UI Components**: Custom component library built on Headless UI
- **State Management**: React hooks and Inertia's built-in state
- **Forms**: Inertia form helpers with client-side validation
- **Rich Text Editor**: TinyMCE 8 for content editing
- **Charts**: Chart.js 4 with react-chartjs-2 for data visualization
- **Icons**: Heroicons and custom SVG icons
- **Drag & Drop**: dnd-kit for sortable lists and reordering
- **SSR**: Server-side rendering enabled for improved SEO and performance

### Development & Testing
- **Local Environment**: DDEV for containerized development
- **Code Quality**: ESLint, Prettier, TypeScript strict mode
- **E2E Testing**: Playwright for browser automation and accessibility testing
- **Package Manager**: bun for efficient dependency management
- **Hot Reload**: Vite HMR with WebSocket support over SSL

### Deployment & CI/CD
- **Containerization**: Docker with multi-stage builds
- **CI/CD**: GitHub Actions for automated testing and deployment
- **Asset Building**: Vite production builds with code splitting and optimization
- **Documentation**: JetBrains Writerside with automated GitHub Pages deployment

## Getting Started

### Prerequisites

- [Docker](https://www.docker.com/get-started)
- [DDEV](https://ddev.readthedocs.io/en/stable/)
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) (v22+)
- [npm](https://www.npmjs.com/)

### Local Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/AkibaAT/fvn.li.git
   cd fvn-li
   ```

2. Start the DDEV environment:
   ```bash
   ddev start
   ```

3. Install PHP dependencies:
   ```bash
   ddev composer install
   ```

4. Install JavaScript dependencies:
   ```bash
   ddev bun install
   ```

5. Copy the environment file and generate an application key:
   ```bash
   cp .env.example .env
   ddev artisan key:generate
   ```

6. Run database migrations:
   ```bash
   ddev artisan migrate
   ```

7. Start the Vite development server:
   ```bash
   ddev bun dev
   ```

   This starts Vite with HMR (Hot Module Replacement) for instant updates during development.

8. Access the application at [https://fvn-li.ddev.site](https://fvn-li.ddev.site)

### Building for Production

To build optimized assets for production:

```bash
# Build client-side assets only
ddev bun build

# Build with SSR support (recommended)
ddev bun build:ssr
```

The build process:
- Compiles TypeScript to optimized JavaScript
- Processes and minifies CSS with Tailwind
- Generates source maps for debugging
- Creates SSR bundle for server-side rendering
- Copies TinyMCE assets to public directory

## Frontend Architecture

The application uses a modern React-based architecture with Inertia.js for seamless server-client communication.

### Project Structure

```
resources/js/
├── app.tsx                 # Main application entry point
├── ssr.tsx                 # Server-side rendering entry point
├── components/             # Reusable React components
│   ├── ui/                # Base UI components (Button, Card, Modal, etc.)
│   ├── layout/            # Layout components (Header, Navigation, Footer)
│   ├── games/             # Game-specific components
│   ├── charts/            # Chart components
│   └── editor/            # Rich text editor components
├── pages/                 # Inertia page components (routes)
│   ├── games/            # Game listing and detail pages
│   ├── dashboard/        # User dashboard pages
│   ├── lists/            # Custom list management
│   └── auth/             # Authentication pages
├── layouts/              # Page layouts
│   ├── PersistentLayout.tsx  # Main layout with persistent header/footer
│   └── SimpleLayout.tsx      # Minimal layout for auth pages
├── hooks/                # Custom React hooks
│   ├── useSearch.ts      # Search functionality
│   ├── useGameFilters.ts # Game filtering logic
│   └── useAccessibility.ts # Accessibility helpers
├── types/                # TypeScript type definitions
└── utils/                # Utility functions
```

### Key Architectural Patterns

**Persistent Layouts**: The application uses a persistent layout system where the header, navigation, and footer remain mounted across page transitions, providing a smooth SPA experience while maintaining Inertia's server-driven routing.

**Component Composition**: UI components are built using composition patterns with Headless UI for accessibility and custom styling with Tailwind CSS.

**Type Safety**: Full TypeScript coverage with strict mode enabled ensures type safety across the entire frontend codebase.

**Server-Side Rendering**: Inertia SSR is enabled for improved initial page load performance and SEO, with automatic hydration on the client side.

**State Management**: Application state is managed through:
- Inertia's shared data for global state (user, flash messages, etc.)
- React hooks for component-local state
- URL parameters for filter and search state

**Form Handling**: Forms use Inertia's form helpers for:
- Automatic CSRF token handling
- Client-side validation
- Progress indicators
- Error handling and display

## Database Structure

The application uses several key models:

- **Game**: Core game information from itch.io
- **GameVersion**: Tracks different versions of games
- **Rater**: Users who rate games
- **Rating**: Individual ratings for games
- **DialogueLine**: Game dialogue content
- **Character**: Characters in games
- **Language**: Supported languages for games
- **DiscordUser**: Discord users subscribed to game updates

## Docker Deployment

The application can be deployed using Docker in production environments:

1. Configure environment variables in `.env`
2. Use the provided `docker-compose.yml` to start the application:
   ```bash
   docker compose up -d
   ```

This will start the following containers:

- Web application (Laravel)
- PostgreSQL database
- Redis for caching

## Discord Bot Integration

The application includes a Discord bot integration that provides:

- Game update notifications for subscribers
- Game search functionality
- User subscription management

Bot API endpoints are available at:

- `/api/search` - Search for games
- `/api/updates` - Get recent game updates
- `/api/subscribe` - Subscribe to update notifications
- `/api/unsubscribe` - Unsubscribe from notifications

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Testing

The application includes comprehensive testing at multiple levels:

### Backend Tests (PHPUnit)

Run the PHP test suite with:

```bash
ddev artisan test
# or
ddev composer test
```

### Frontend E2E Tests (Playwright)

End-to-end tests using Playwright for browser automation:

```bash
# Run all E2E tests
ddev bun test:e2e

# Run tests in UI mode (interactive)
ddev bun test:e2e:ui

# Run accessibility tests
ddev bun test:a11y

# View test report
ddev bun test:a11y:report
```

Playwright tests cover:
- User flows and interactions
- Accessibility compliance (WCAG)
- Cross-browser compatibility
- Visual regression testing

### Code Quality

```bash
# Run TypeScript type checking
ddev bun types

# Run ESLint
ddev bun lint

# Format code with Prettier
ddev bun format

# Check formatting without changes
ddev bun format:check
```

## Deployment

The application is deployed at [FVN.li](https://fvn.li). Deployment is handled through GitHub Actions which builds and
publishes Docker images to GitHub Container Registry.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgements

### Backend Technologies
- [Laravel](https://laravel.com) - The PHP web framework
- [PostgreSQL](https://www.postgresql.org) - Relational database
- [Redis](https://redis.io) - In-memory data store for caching and queues
- [Meilisearch](https://www.meilisearch.com) - Fast, typo-tolerant search engine

### Frontend Technologies
- [React](https://react.dev) - UI library
- [Inertia.js](https://inertiajs.com) - Modern monolith framework
- [Vite](https://vitejs.dev) - Next-generation frontend build tool
- [TypeScript](https://www.typescriptlang.org) - Typed JavaScript
- [Tailwind CSS](https://tailwindcss.com) - Utility-first CSS framework
- [Headless UI](https://headlessui.com) - Unstyled, accessible UI components
- [Chart.js](https://www.chartjs.org) - Simple yet flexible charting library
- [TinyMCE](https://www.tiny.cloud) - Rich text editor
- [Heroicons](https://heroicons.com) - Beautiful hand-crafted SVG icons

### Development Tools
- [DDEV](https://ddev.com) - Docker-based local development environment
- [Playwright](https://playwright.dev) - End-to-end testing framework
- [ESLint](https://eslint.org) - JavaScript linter
- [Prettier](https://prettier.io) - Code formatter

### Platform & Integration
- [itch.io](https://itch.io) - The game distribution platform this project tracks
- [Discord](https://discord.com) - Platform for bot integration and notifications
- [GitHub Actions](https://github.com/features/actions) - CI/CD automation

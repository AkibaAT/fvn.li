# FVN.li - Visual Novel Analytics and Tracking

[![Deploy Writerside Documentation](https://github.com/AkibaAT/fvn.li/actions/workflows/deploy-docs.yml/badge.svg)](https://github.com/AkibaAT/fvn.li/actions/workflows/deploy-docs.yml)

FVN.li is a web application that tracks, analyzes, and provides insights into games published on itch.io. It collects
data about games, their versions, ratings, and dialogue content, making it easier for users to discover and evaluate
games on the platform. The project is deployed and accessible at [FVN.li](https://fvn.li).

## Documentation

📚 **[View Documentation](https://akibaat.github.io/fvn.li/)** - Comprehensive documentation built with Writerside and
deployed to GitHub Pages.

## Features

- **Game Tracking**: Monitor games published on itch.io, including metadata, versions, and ratings
- **Dialogue Browser**: Explore game dialogue content across different versions and languages
- **Rating System**: View and analyze game ratings from the community
- **Language Support**: Track supported languages for games and analyze translation coverage
- **Character Statistics**: View character statistics and dialogue distribution
- **Discord Bot Integration**: Get notified about game updates via Discord

## Tech Stack

- **Backend**: Laravel 13.7 with PHP 8.5
- **Frontend**: Svelte 5.55 with TypeScript, Inertia.js 2.3, Tailwind CSS 4.2
- **Build Tool**: Vite 8 with SSR support
- **Database**: PostgreSQL 17
- **Search**: Meilisearch for full-text search
- **Caching**: Redis
- **Development**: DDEV for local development environment
- **Visualization**: Chart.js with Svelte chart components for data visualization
- **Testing**: PHPUnit/Pest, Vitest for Svelte units, and Playwright for E2E/accessibility testing through the DDEV Playwright sidecar
- **Deployment**: Docker for containerized deployment
- **API**: RESTful API endpoints for Discord bot integration

For detailed architecture information, see the [Frontend Architecture](https://akibaat.github.io/fvn.li/frontend-architecture.html) documentation.

## Getting Started

### Prerequisites

- [Docker](https://www.docker.com/get-started)
- [DDEV](https://ddev.readthedocs.io/en/stable/)
- [Composer](https://getcomposer.org/)
- [Bun](https://bun.sh/)

### Local Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/AkibaAT/fvn.li.git
   cd fvn.li
   ```

2. Configure local `butler-server` checkout paths:
   ```bash
   cat .ddev/butler-server/host-paths.env.example >> .ddev/.env
   ```

   Edit `.ddev/.env` so `FVN_BUTLER_CLIENT_HOST_PATH`, `FVN_BUTLER_SERVER_HOST_PATH`, and `FVN_WHARF_HOST_PATH`
   point to absolute paths for your local `butler-client`, `butler-server`, and `wharf` checkouts.

3. Start the DDEV environment:
   ```bash
   ddev start
   ```

4. Install PHP dependencies:
   ```bash
   ddev composer install
   ```

5. Install JavaScript dependencies:
   ```bash
   ddev bun install
   ```

6. Copy the environment file and generate an application key:
   ```bash
   cp .env.example .env
   ddev artisan key:generate
   ```

7. Run database migrations:
   ```bash
   ddev artisan migrate
   ```

8. Start the Vite development server:
   ```bash
   ddev bun dev
   ```

9. Access the application at [https://fvn-li.ddev.site](https://fvn-li.ddev.site)

For more detailed development instructions, see the [Development Guide](https://akibaat.github.io/fvn.li/development-guide.html).

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

### Backend Tests (PHPUnit)

Run the test suite with the testing environment (served at https://fvn-li-testing.ddev.site):

```bash
ddev artisan test --env=testing
# or, via Composer script (also uses --env=testing)
ddev composer test
```

For coverage, use:

```bash
ddev composer test:coverage:clover
ddev composer test:coverage:audit
```

Reset the testing DB when needed:

```bash
ddev composer migrate:test
```

### Frontend Tests

Run Svelte unit tests with Vitest:

```bash
ddev bun test:js
```

### Frontend E2E Tests (Playwright)

Playwright runs against the official Playwright sidecar service configured in DDEV. Browser dependencies are not installed in the web container.

```bash
# Run all E2E tests
ddev playwright test

# Run in UI mode (interactive)
ddev playwright test --ui

# Run accessibility tests
ddev playwright test tests/e2e/specs/accessibility.spec.ts --grep @accessibility

# View test report
ddev bun test:a11y:report
```

### Code Quality

```bash
# TypeScript type checking
ddev bun types

# ESLint
ddev bun lint

# Prettier formatting
ddev bun format
ddev bun format:check
```

## DDEV Conventions

- Run Composer and bun inside DDEV: `ddev composer <cmd>`, `ddev bun <cmd>`.
- PHP Linting: `ddev composer lint` (PHP/Duster), `ddev composer lint:fix`.
- Frontend Linting: `ddev bun lint`, `ddev bun format`.
- Git hooks run linters and tests via DDEV. Enable with `composer hooks:install` and ensure hooks are executable.
- Testing URL: `fvn-li-testing.ddev.site` is configured via DDEV `additional_fqdns`. Run `ddev restart` after pulling
  config changes.
- Dev URL: `fvn-li.ddev.site` (default DDEV project URL).

## Deployment

The application is deployed at [FVN.li](https://fvn.li). Deployment is handled through GitHub Actions which builds and
publishes Docker images to GitHub Container Registry.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgements

- [Laravel](https://laravel.com) - PHP web framework
- [Svelte](https://svelte.dev) - UI framework
- [Inertia.js](https://inertiajs.com) - Modern monolith framework
- [Vite](https://vitejs.dev) - Frontend build tool
- [TypeScript](https://www.typescriptlang.org) - Typed JavaScript
- [Tailwind CSS](https://tailwindcss.com) - CSS framework
- [Chart.js](https://www.chartjs.org) - Charting library
- [Playwright](https://playwright.dev) - E2E testing framework
- [itch.io](https://itch.io) - Game distribution platform
- [Discord](https://discord.com) - Bot integration platform
- [DDEV](https://ddev.com) - Local development environment

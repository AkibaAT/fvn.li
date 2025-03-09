# FVN.li - Visual Novel Analytics and Tracking

FVN.li is a web application that tracks, analyzes, and provides insights into games published on itch.io. It collects data about games, their versions, ratings, and dialogue content, making it easier for users to discover and evaluate games on the platform. The project is deployed and accessible at [FVN.li](https://fvn.li).

## Features

- **Game Tracking**: Monitor games published on itch.io, including metadata, versions, and ratings
- **Dialogue Browser**: Explore game dialogue content across different versions and languages
- **Rating System**: View and analyze game ratings from the community
- **Language Support**: Track supported languages for games and analyze translation coverage
- **Character Statistics**: View character statistics and dialogue distribution

## Tech Stack

- **Backend**: Laravel 12 with PHP 8.4
- **Frontend**: Livewire, TypeScript, Tailwind CSS
- **Database**: PostgreSQL 17
- **Caching**: Redis
- **Development**: DDEV for local development environment
- **Visualization**: ECharts for data visualization

## Getting Started

### Prerequisites

- [Docker](https://www.docker.com/get-started)
- [DDEV](https://ddev.readthedocs.io/en/stable/)
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) (v18+)
- [npm](https://www.npmjs.com/) or [yarn](https://yarnpkg.com/)

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
   ddev npm install
   # or
   ddev yarn
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

7. Start the development server:
   ```bash
   ddev artisan serve
   # In another terminal
   ddev npm run dev
   ```

8. Access the application at [https://fvn-li.ddev.site](https://fvn-li.ddev.site)

## Database Structure

The application uses several key models:
- **Game**: Core game information from itch.io
- **GameVersion**: Tracks different versions of games
- **Rater**: Users who rate games
- **Rating**: Individual ratings for games
- **DialogueLine**: Game dialogue content
- **Character**: Characters in games
- **Language**: Supported languages for games

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Testing

Run the test suite with:

```bash
ddev artisan test
# or
ddev composer test
```

## Deployment

The application is deployed at [FVN.li](https://fvn.li). It can be deployed using Docker in production environments. Configuration for production deployment is available in the `.ddev` directory.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgements

- [Laravel](https://laravel.com) - The web framework used
- [itch.io](https://itch.io) - The game distribution platform this project tracks
- [DDEV](https://ddev.com) - Local development environment
- [Livewire](https://livewire.laravel.com) - Full-stack framework for Laravel
- [Tailwind CSS](https://tailwindcss.com) - CSS framework
- [ECharts](https://echarts.apache.org) - Charting library

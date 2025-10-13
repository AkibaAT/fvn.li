# FVN.li Project Overview

FVN.li is a visual novel analytics and tracking platform built with Laravel 12 and PHP 8.4. The application tracks, analyzes, and provides insights into games published on itch.io, collecting data about games, their versions, ratings, and dialogue content.

## Tech Stack

- **Backend**: Laravel 12 with PHP 8.4
- **Frontend**: React (Inertia.js), TypeScript, Tailwind CSS
- **Database**: PostgreSQL 17 with PgBouncer  
- **Caching**: Redis
- **Development**: DDEV for local development environment
- **Visualization**: ECharts for data visualization
- **Rich Text Editor**: TinyMCE for content editing
- **Deployment**: Docker for containerized deployment

## Key Features

- Game tracking and metadata from itch.io
- Dialogue browser across different versions and languages
- Rating system and analytics
- Language support tracking
- Character statistics
- Discord bot integration
- Customizable game content with rich text editing

## Content Editing System

The application includes a sophisticated content editing system using TinyMCE:

- **TinyMCEEditor component**: React wrapper for TinyMCE with dark mode support
- **EditableGameContent component**: Handles editable game descriptions with save/cancel functionality
- **EditorUploadController**: Handles image uploads for the editor with permission checks
- **Image handling**: Supports drag-and-drop, paste, and gallery integration
- **Auto-save**: Content is saved via AJAX to `/api/games/{game}/content` endpoint

## Directory Structure

- `/app/Models/`: Eloquent models (Game, GameVersion, Rating, DialogueLine, Character, etc.)
- `/app/Http/Controllers/`: Web and API controllers
- `/app/Services/`: Business logic services
- `/app/Filament/`: Admin panel resources and pages
- `/resources/js/components/editor/`: Rich text editor components
- `/resources/js/pages/`: React page components
- `/database/migrations/`: Database schema

## Development Commands

```bash
# Start DDEV environment
ddev start

# Install dependencies
ddev composer install
ddev npm install

# Run development server
ddev npm run dev

# Database operations
ddev artisan migrate
ddev artisan tinker

# Testing
ddev artisan test --env=testing
ddev composer test

# Linting and formatting
ddev composer lint
ddev npm run lint
ddev composer lint:fix
```
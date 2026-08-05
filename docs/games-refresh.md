# games:refresh

Refreshes game information from itch.io for specific games or all visible games.

## Overview

This command updates game information by fetching the latest data from itch.io. It can refresh various aspects of game
data including basic information, version details, and metadata like tags, ratings, and screenshots.

## Usage

```bash
php artisan games:refresh [options]
```

## Options

--game-id=ID
:   ID of the specific game to refresh.

--game-name=NAME
:   Name (or part of name) of the game(s) to refresh.

--all
:   Refresh all visible games.

--limit=LIMIT
:   Limit the number of games to process when using --all (default: 10).

--sort=FIELD
:   Sort games by field (id, name, created_at, updated_at) (default: id).

--update-version
:   Refresh version information.

--update-info
:   Refresh base game information.

--update-metadata
:   Refresh metadata (tags, ratings, descriptions, screenshots, game jams).

--force
:   Force refresh even for abandoned/canceled games.

--max-retries=COUNT
:   Maximum number of retries for rate-limited requests (default: 3).

--retry-cooldown=SECONDS
:   Base cooldown time in seconds between retries (default: 30).

## Refresh Types

The command can refresh different aspects of game data:

### Basic Information (`--update-info`)

- Game title and description
- Developer/creator information
- Publication status
- Basic game metadata

### Version Information (`--update-version`)

- Latest version details
- Download links and file information
- Version-specific metadata
- Platform support information

### Metadata (`--update-metadata`)

- Tags and categories
- User ratings and reviews
- Screenshots and media
- Game jam associations
- Detailed descriptions

## Examples

=== "Specific Game"

    ```bash
    php artisan games:refresh --game-id=123
    ```
    <p>Refreshes all information for game ID 123.</p>

=== "By Name"

    ```bash
    php artisan games:refresh --game-name="Doki Doki"
    ```
    <p>Refreshes all games with "Doki Doki" in the name.</p>

=== "Version Only"

    ```bash
    php artisan games:refresh --game-id=123 --update-version
    ```
    <p>Only refreshes version information for the specified game.</p>

=== "Metadata Only"

    ```bash
    php artisan games:refresh --all --update-metadata --limit=50
    ```
    <p>Refreshes metadata for up to 50 games.</p>

=== "Force Refresh"

    ```bash
    php artisan games:refresh --game-id=123 --force
    ```
    <p>Forces refresh even if the game is marked as abandoned.</p>

## When to Use

**Recommended Usage Scenarios**

1. After discovering games need updated information
2. When game metadata appears outdated
3. To refresh specific games after manual changes
4. For bulk updates during maintenance windows
5. When troubleshooting data inconsistencies

## Processing Modes

<table>
<tr>
    <td>Mode</td>
    <td>Description</td>
    <td>Performance Impact</td>
</tr>
<tr>
    <td>Single Game</td>
    <td>Refresh one specific game</td>
    <td>Low</td>
</tr>
<tr>
    <td>By Name</td>
    <td>Refresh games matching pattern</td>
    <td>Medium</td>
</tr>
<tr>
    <td>Batch (--all)</td>
    <td>Refresh multiple games</td>
    <td>High</td>
</tr>
<tr>
    <td>Selective Updates</td>
    <td>Only specific data types</td>
    <td>Reduced</td>
</tr>
</table>

## Rate Limiting

The command includes comprehensive rate limiting handling:

- **Automatic Detection**: Recognizes 429 responses from itch.io
- **Exponential Backoff**: Increasing delays between retries
- **Configurable Retries**: Adjustable retry count and cooldown
- **Progress Preservation**: Continues from where it left off

## Related Commands

- [games:refresh-feedless](games-refresh-feedless.md) - Refresh games not in feed
- [feed:process](feed-process.md) - Automatic feed-based updates
- [games:process-screenshots](games-process-screenshots.md) - Process downloaded screenshots

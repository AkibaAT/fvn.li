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

<deflist>
<def title="--game-id=ID">
ID of the specific game to refresh.
</def>
<def title="--game-name=NAME">
Name (or part of name) of the game(s) to refresh.
</def>
<def title="--all">
Refresh all visible games.
</def>
<def title="--limit=LIMIT">
Limit the number of games to process when using --all (default: 10).
</def>
<def title="--sort=FIELD">
Sort games by field (id, name, created_at, updated_at) (default: id).
</def>
<def title="--update-version">
Refresh version information.
</def>
<def title="--update-info">
Refresh base game information.
</def>
<def title="--update-metadata">
Refresh metadata (tags, ratings, descriptions, screenshots, game jams).
</def>
<def title="--force">
Force refresh even for abandoned/canceled games.
</def>
<def title="--max-retries=COUNT">
Maximum number of retries for rate-limited requests (default: 3).
</def>
<def title="--retry-cooldown=SECONDS">
Base cooldown time in seconds between retries (default: 30).
</def>
</deflist>

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

<tabs>
<tab title="Specific Game">
<code-block lang="bash">
php artisan games:refresh --game-id=123
</code-block>
<p>Refreshes all information for game ID 123.</p>
</tab>
<tab title="By Name">
<code-block lang="bash">
php artisan games:refresh --game-name="Doki Doki"
</code-block>
<p>Refreshes all games with "Doki Doki" in the name.</p>
</tab>
<tab title="Version Only">
<code-block lang="bash">
php artisan games:refresh --game-id=123 --update-version
</code-block>
<p>Only refreshes version information for the specified game.</p>
</tab>
<tab title="Metadata Only">
<code-block lang="bash">
php artisan games:refresh --all --update-metadata --limit=50
</code-block>
<p>Refreshes metadata for up to 50 games.</p>
</tab>
<tab title="Force Refresh">
<code-block lang="bash">
php artisan games:refresh --game-id=123 --force
</code-block>
<p>Forces refresh even if the game is marked as abandoned.</p>
</tab>
</tabs>

## When to Use

<procedure title="Recommended Usage Scenarios">
<step>After discovering games need updated information</step>
<step>When game metadata appears outdated</step>
<step>To refresh specific games after manual changes</step>
<step>For bulk updates during maintenance windows</step>
<step>When troubleshooting data inconsistencies</step>
</procedure>

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

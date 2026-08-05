# games:update-watchlist

Updates games from itch.io collection and follows creators automatically.

## Overview

This command synchronizes the local database with games from itch.io collections and automatically follows creators of
those games. It's designed to maintain a curated watchlist of games and ensure comprehensive coverage of followed
creators' work.

## Usage

```bash
php artisan games:update-watchlist [options]
```

## Options

The command supports various options for controlling collection updates and creator following behavior.

## Update Process

The command follows this workflow:

1. **Fetches collection data** from itch.io
2. **Identifies new games** not yet in the database
3. **Adds missing games** to the local database
4. **Updates existing games** with fresh information
5. **Follows game creators** automatically
6. **Discovers creator catalogs** for additional games
7. **Reports update statistics** for monitoring

## Examples

=== "Standard Update"

    ```bash
    php artisan games:update-watchlist
    ```
    <p>Updates the watchlist from configured collections.</p>

=== "Specific Collection"

    ```bash
    php artisan games:update-watchlist --collection-id=12345
    ```
    <p>Updates from a specific itch.io collection.</p>

=== "Creator Discovery"

    ```bash
    php artisan games:update-watchlist --follow-creators
    ```
    <p>Includes automatic creator following and catalog discovery.</p>

=== "Limited Update"

    ```bash
    php artisan games:update-watchlist --limit=50
    ```
    <p>Processes only the first 50 games from collections.</p>

## When to Use

**Recommended Usage Scenarios**

1. Scheduled execution to maintain current watchlist
2. After adding new collections to monitor
3. When discovering new creators to follow
4. During initial database population
5. For comprehensive creator catalog updates

## Collection Types

The command can work with various itch.io collection types:

### Public Collections

- **Curated Lists**: Hand-picked games by topic or quality
- **Community Collections**: User-generated game lists
- **Featured Collections**: Promoted or highlighted games

### Personal Collections

- **User Libraries**: Games owned or followed by users
- **Wishlists**: Games marked for future consideration
- **Custom Collections**: User-created organizational lists

### Automated Collections

- **Tag-Based**: Collections based on game tags or categories
- **Creator-Based**: All games from specific creators
- **Time-Based**: Recent releases or updates

## Creator Following

The watchlist update includes intelligent creator following:

### Automatic Discovery

- **Game Creators**: Follows developers and publishers of watchlist games
- **Catalog Expansion**: Discovers other games by followed creators
- **Relationship Mapping**: Tracks creator-game relationships

### Following Strategy

- **Quality Filtering**: Applies quality thresholds for automatic following
- **Category Preferences**: Respects content category preferences
- **Update Frequency**: Manages how often to check for new creator content

## Data Synchronization

<table>
<tr>
    <td>Data Type</td>
    <td>Update Frequency</td>
    <td>Priority</td>
</tr>
<tr>
    <td>New Games</td>
    <td>Every update</td>
    <td>High</td>
</tr>
<tr>
    <td>Game Metadata</td>
    <td>Weekly</td>
    <td>Medium</td>
</tr>
<tr>
    <td>Creator Info</td>
    <td>Monthly</td>
    <td>Medium</td>
</tr>
<tr>
    <td>Collection Changes</td>
    <td>Every update</td>
    <td>High</td>
</tr>
</table>

## Quality Control

The update process includes quality control measures:

### Game Filtering

- **Content Quality**: Applies quality thresholds and filters
- **Category Restrictions**: Respects content category preferences
- **Language Requirements**: Filters by supported languages

### Creator Validation

- **Activity Levels**: Considers creator activity and engagement
- **Content Quality**: Evaluates overall creator portfolio quality
- **Community Standing**: Considers creator reputation and reviews

## Performance Optimization

The command is optimized for efficient operation:

### API Efficiency

- **Batch Requests**: Groups API calls for efficiency
- **Caching**: Caches collection and creator data
- **Rate Limiting**: Respects itch.io API limits

### Database Optimization

- **Bulk Operations**: Uses efficient bulk insert/update operations
- **Index Usage**: Leverages database indexes for fast lookups
- **Transaction Management**: Groups related operations in transactions

## Integration

Watchlist updates integrate with other systems:

### Notification System

- **Update Alerts**: Triggers notifications for new games
- **Creator Updates**: Notifies about new releases from followed creators
- **Collection Changes**: Alerts about collection modifications

### Game Processing

- **Automatic Import**: Triggers import of newly discovered games
- **Metadata Refresh**: Schedules metadata updates for new games
- **Analysis Queue**: Adds new games to analysis pipeline

> **Note**: Watchlist updates can discover large numbers of new games, potentially triggering significant downstream
> processing.

## Related Commands

- [games:refresh](games-refresh.md) - Refresh discovered games
- [feed:process](feed-process.md) - Process feed updates for followed creators
- [notifications:queue-game-updates](notifications-queue-game-updates.md) - Notify about watchlist
  updates

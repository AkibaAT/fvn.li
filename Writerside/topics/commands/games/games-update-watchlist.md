# games:update-watchlist

Updates games from itch.io collection and follows creators automatically.

## Overview

This command synchronizes the local database with games from itch.io collections and automatically follows creators of
those games. It's designed to maintain a curated watchlist of games and ensure comprehensive coverage of followed
creators' work.

**Key Features**: Collection synchronization, creator following, automatic discovery, relationship management.

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

<tabs>
<tab title="Standard Update">
<code-block lang="bash">
php artisan games:update-watchlist
</code-block>
<p>Updates the watchlist from configured collections.</p>
</tab>
<tab title="Specific Collection">
<code-block lang="bash">
php artisan games:update-watchlist --collection-id=12345
</code-block>
<p>Updates from a specific itch.io collection.</p>
</tab>
<tab title="Creator Discovery">
<code-block lang="bash">
php artisan games:update-watchlist --follow-creators
</code-block>
<p>Includes automatic creator following and catalog discovery.</p>
</tab>
<tab title="Limited Update">
<code-block lang="bash">
php artisan games:update-watchlist --limit=50
</code-block>
<p>Processes only the first 50 games from collections.</p>
</tab>
</tabs>

## When to Use

<procedure title="Recommended Usage Scenarios">
<step>Scheduled execution to maintain current watchlist</step>
<step>After adding new collections to monitor</step>
<step>When discovering new creators to follow</step>
<step>During initial database population</step>
<step>For comprehensive creator catalog updates</step>
</procedure>

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

## Monitoring and Reporting

The command provides comprehensive reporting:

### Update Statistics

- **Games Added**: Number of new games discovered
- **Games Updated**: Existing games with refreshed information
- **Creators Followed**: New creators added to following list
- **Collections Processed**: Number of collections synchronized

### Quality Metrics

- **Success Rate**: Percentage of successful operations
- **Error Rate**: Failed operations requiring attention
- **Data Completeness**: Coverage and quality of imported data

## Error Handling

Robust error handling ensures reliable operation:

### Collection Issues

- **Access Problems**: Handles private or restricted collections
- **Format Changes**: Adapts to itch.io collection format changes
- **Missing Data**: Gracefully handles incomplete collection information

### Creator Issues

- **Profile Access**: Manages private or restricted creator profiles
- **Catalog Changes**: Handles changes in creator game catalogs
- **Following Limits**: Respects platform limits on following

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

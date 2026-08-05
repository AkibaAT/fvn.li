# ratings:import

Imports the latest ratings data from itch.io for all tracked games.

## Overview

This command fetches current ratings information from itch.io and updates the local database with the latest user
ratings, scores, and review counts. It ensures the ratings data stays synchronized with itch.io's current state.

## Usage

```bash
php artisan ratings:import
```

## Options

This command has no specific options. It automatically imports ratings for all tracked games using configured settings.

## Import Process

The command follows this workflow:

1. **Identifies games** requiring ratings updates
2. **Fetches ratings data** from itch.io API
3. **Validates data integrity** before import
4. **Updates database records** with new ratings
5. **Calculates aggregate scores** and statistics
6. **Reports import statistics** for monitoring

## Examples

=== "Standard Import"

    ```bash
    php artisan ratings:import
    ```
    <p>Imports latest ratings for all tracked games.</p>

=== "Verbose Output"

    ```bash
    php artisan ratings:import -v
    ```
    <p>Shows detailed import progress and statistics.</p>

=== "Quiet Mode"

    ```bash
    php artisan ratings:import --quiet
    ```
    <p>Only shows errors, useful for automated execution.</p>

## When to Use

**Recommended Usage Scenarios**

1. Scheduled execution (daily or weekly) for regular updates
2. Manual execution after discovering rating discrepancies
3. Initial import for newly added games
4. Recovery after extended downtime

## Data Imported

The command imports comprehensive ratings information:

### Individual Ratings

- **User Scores**: Numerical ratings from individual users
- **Review Text**: Written reviews and comments
- **Rating Dates**: When ratings were submitted
- **User Information**: Anonymized user identifiers

### Aggregate Data

- **Average Scores**: Calculated average ratings
- **Rating Counts**: Total number of ratings
- **Score Distribution**: Breakdown by rating value
- **Trend Information**: Rating changes over time

## Data Validation

The import process includes validation:

### Source Validation

- **API Response**: Ensures valid JSON from itch.io
- **Data Completeness**: Verifies required fields are present
- **Format Consistency**: Validates data types and ranges

### Content Validation

- **Score Ranges**: Ensures ratings are within valid ranges
- **Date Validation**: Verifies rating timestamps are reasonable
- **Duplicate Detection**: Prevents duplicate rating imports

## Performance Optimization

The command is optimized for efficiency:

- **Incremental Updates**: Only imports changed ratings
- **Batch Processing**: Groups API calls for efficiency
- **Database Optimization**: Uses efficient queries and indexes
- **Memory Management**: Handles large datasets without memory issues

## Integration

The ratings import integrates with other systems:

- **Game Refresh**: Triggered during comprehensive game updates
- **Feed Processing**: Updates ratings when games are updated
- **User Interface**: Provides fresh data for game displays
- **Analytics**: Feeds rating trends and statistics

> **Note**: Import frequency should balance data freshness with API usage limits and system resources.

## Related Commands

- [ratings:backfill](backfill.md): Fill gaps in historical ratings data
- [games:refresh](../games/refresh.md): Comprehensive game data updates including ratings

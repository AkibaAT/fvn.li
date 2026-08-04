# ratings:recalculate

Recalculate rating totals for all games from individual Rating records.

## Synopsis

```bash
php artisan ratings:recalculate [--game=ID]
```

## Description

This command recalculates the aggregated rating statistics for games based on individual rating records in the database. It updates the denormalized rating fields on the `games` table to ensure they match the actual rating data.

This is useful for:
- Fixing rating inconsistencies
- Recovering from data corruption
- Updating after bulk rating imports
- Maintenance and verification

## Options

### `--game=ID`

Recalculate ratings for a specific game only.

```bash
# Recalculate for game ID 123
php artisan ratings:recalculate --game=123
```

Without this option, all games are recalculated.

## Usage Examples

### Recalculate All Games

Recalculate ratings for all games:

```bash
php artisan ratings:recalculate
```

Output:
```
Recalculating ratings for all games...
Processing 10,234 games...

Progress: 1000/10234 [=====>----------------------] 9%
Progress: 2000/10234 [==========>-----------------] 19%
...

Recalculated ratings for 10,234 games
Updated 8,456 games with changes
1,778 games unchanged

Summary:
  - Total ratings processed: 1,234,567
  - Average rating: 4.2
  - Games with ratings: 8,456
  - Games without ratings: 1,778
```

### Recalculate Single Game

Recalculate ratings for a specific game:

```bash
php artisan ratings:recalculate --game=123
```

Output:
```
Recalculating ratings for game #123...

Game: "Example Visual Novel"
  - Total ratings: 456
  - Average rating: 4.5
  - Rating distribution:
    5 stars: 234 (51%)
    4 stars: 123 (27%)
    3 stars: 67 (15%)
    2 stars: 23 (5%)
    1 star: 9 (2%)

Ratings recalculated successfully
```

### DDEV Environment

Run in DDEV:

```bash
ddev artisan ratings:recalculate
```

## What It Recalculates

### Rating Fields

The command updates the following fields on the `games` table:

#### `rating_average`
Average rating score (1-5 scale):

```sql
AVG(rating) FROM ratings WHERE game_id = ?
```

#### `rating_count`
Total number of ratings:

```sql
COUNT(*) FROM ratings WHERE game_id = ?
```

#### `rating_distribution`
JSON object with rating counts:

```json
{
  "1": 9,
  "2": 23,
  "3": 67,
  "4": 123,
  "5": 234
}
```

#### `weighted_rating`
Weighted rating using Bayesian average:

```
(C × m + Σ(rating)) / (C + n)

Where:
  C = confidence factor (e.g., 10)
  m = mean rating across all games
  n = number of ratings for this game
  Σ(rating) = sum of all ratings for this game
```

## How It Works

### 1. Fetch Games

Retrieves games to process:
- All games (default)
- Single game (with `--game` option)

### 2. Calculate Statistics

For each game:
1. Counts total ratings
2. Calculates average rating
3. Builds rating distribution
4. Computes weighted rating

### 3. Update Database

Updates the game record with calculated values:

```sql
UPDATE games 
SET rating_average = ?,
    rating_count = ?,
    rating_distribution = ?,
    weighted_rating = ?,
    updated_at = NOW()
WHERE id = ?
```

### 4. Report Results

Displays summary of changes and statistics.

## When to Use

### Required Scenarios

Run `ratings:recalculate` when:

1. **After Bulk Import**: After running `ratings:import` or `ratings:backfill`
2. **Data Inconsistencies**: Rating totals don't match individual ratings
3. **Database Recovery**: Recovering from backup or migration
4. **Manual Rating Changes**: After manually modifying rating records
5. **Suspicious Activity**: After removing suspicious ratings

### Maintenance

Run periodically (e.g., monthly) to ensure data integrity.

## Performance

### Execution Time

| Games | Estimated Time |
|-------|----------------|
| 100   | < 1 minute     |
| 1,000 | 2-3 minutes    |
| 10,000| 10-15 minutes  |
| 50,000| 30-60 minutes  |

### Resource Usage

- **CPU**: Moderate (calculations)
- **Memory**: Low (processes in batches)
- **Database**: Moderate (reads + writes)
- **I/O**: Moderate

### Optimization

The command uses batched processing:
- Processes 100 games at a time
- Minimizes memory usage
- Prevents timeout issues

## Verification

### Check Results

Verify recalculation worked:

```sql
-- Check a specific game
SELECT 
    id,
    title,
    rating_average,
    rating_count,
    rating_distribution
FROM games 
WHERE id = 123;

-- Compare with actual ratings
SELECT 
    COUNT(*) as count,
    AVG(rating) as average,
    rating,
    COUNT(*) as distribution
FROM ratings 
WHERE game_id = 123
GROUP BY rating;
```

### Validate Consistency

Check for inconsistencies:

```sql
-- Find games where count doesn't match
SELECT g.id, g.title, g.rating_count, COUNT(r.id) as actual_count
FROM games g
LEFT JOIN ratings r ON r.game_id = g.id
GROUP BY g.id
HAVING g.rating_count != COUNT(r.id);
```

## Impact on System

### Database Changes

Only updates the `games` table:
- No changes to `ratings` table
- No changes to user data
- Preserves all historical data

### Search Index

If using Meilisearch, ratings are automatically updated in the search index via model observers.

### Caching

May need to clear caches after recalculation:

```bash
php artisan cache:clear
```

## Troubleshooting

### Slow Performance

**Problem**: Recalculation is very slow

**Solutions**:
1. Run during off-peak hours
2. Use `--game` option for specific games
3. Increase batch size in code
4. Check database indexes
5. Optimize database queries

### Memory Issues

**Problem**: Out of memory errors

**Solutions**:
1. Increase PHP memory limit
2. Reduce batch size
3. Process games in smaller chunks
4. Restart PHP-FPM

### Inconsistent Results

**Problem**: Results don't match expectations

**Solutions**:
1. Verify rating data in database
2. Check for orphaned ratings
3. Look for duplicate ratings
4. Verify calculation logic

### Database Locks

**Problem**: Database lock timeouts

**Solutions**:
1. Run during low-traffic periods
2. Reduce batch size
3. Check for long-running queries
4. Optimize database configuration

## Best Practices

1. **After Imports**: Always run after bulk rating imports
2. **Regular Schedule**: Run monthly for data integrity
3. **Backup First**: Backup database before large recalculations
4. **Monitor Progress**: Watch output for errors
5. **Verify Results**: Spot-check results after completion
6. **Off-Peak Hours**: Run during low-traffic periods

## Related Commands

- [ratings:import](ratings-import.md) - Import latest ratings from itch.io
- [ratings:backfill](ratings-backfill.md) - Backfill missing ratings
- [rater:mark-suspicious](rater-mark-suspicious.md) - Mark suspicious raters

## See Also

- [Ratings Commands Overview](ratings-commands-overview.md)
- Game model rating fields
- Rating calculation algorithms


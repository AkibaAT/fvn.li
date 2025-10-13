# games:check-suspended

Check visible games for suspension status by fetching their project pages.

## Synopsis

```bash
php artisan games:check-suspended
```

## Description

This command checks all visible games in the database to verify if they have been suspended on itch.io. It fetches each game's project page and updates the `is_suspended` flag accordingly.

Suspended games are:
- Hidden from public listings
- Excluded from search results
- Skipped during data refresh operations
- Marked with `is_suspended = true` in the database

This command helps maintain data integrity by identifying games that are no longer available on itch.io.

## Usage Examples

### Basic Usage

Check all visible games for suspension:

```bash
php artisan games:check-suspended
```

Output:
```
Checking suspension status for visible games...
Processing 10,234 games...

Progress: 1000/10234 [=====>----------------------] 9%
Progress: 2000/10234 [==========>-----------------] 19%
...

✓ Checked 10,234 games
✓ Found 45 newly suspended games
✓ Updated suspension status

Newly suspended games:
  - Game #1234: "Example Game" (https://example.itch.io/game)
  - Game #5678: "Another Game" (https://another.itch.io/game)
  ...
```

### DDEV Environment

Run in DDEV:

```bash
ddev artisan games:check-suspended
```

### Scheduled Execution

This command is typically run on a schedule (e.g., daily or weekly) to keep suspension status up to date.

## What It Does

### 1. Fetch Visible Games

Retrieves all games where `is_suspended = false` from the database.

### 2. Check Each Game

For each game:
1. Fetches the game's itch.io project page
2. Checks for suspension indicators (404, removed, etc.)
3. Updates `is_suspended` flag if suspended

### 3. Report Results

Displays:
- Total games checked
- Number of newly suspended games
- List of suspended games with URLs

## Suspension Detection

The command detects suspension through:

- **404 Errors**: Game page returns 404 Not Found
- **Removed Pages**: Page indicates game was removed
- **Access Denied**: Page shows access restrictions
- **Other Indicators**: Various itch.io suspension messages

## Impact on System

### Database Updates

When a game is marked as suspended:

```sql
UPDATE games 
SET is_suspended = true, 
    updated_at = NOW() 
WHERE id = ?
```

### Automatic Exclusions

Suspended games are automatically excluded from:

1. **Search Results**: Not returned in Meilisearch queries
2. **Game Listings**: Hidden from public game lists
3. **Feed Processing**: Skipped during `feed:process`
4. **Refresh Operations**: Excluded from `games:refresh`
5. **Stats Import**: Not processed by `games:import-stats`

### User Impact

- Games in user lists remain but show as suspended
- Ratings and reviews are preserved
- Historical data is maintained
- Game can be unsuspended if restored on itch.io

## Performance

### Execution Time

- **Small dataset** (< 1,000 games): 5-10 minutes
- **Medium dataset** (1,000-10,000 games): 30-60 minutes
- **Large dataset** (> 10,000 games): 1-2 hours

Time varies based on:
- Number of games to check
- Network latency to itch.io
- Rate limiting delays

### Resource Usage

- **Network**: High (fetches each game page)
- **CPU**: Low (mostly I/O bound)
- **Memory**: Low (processes in batches)
- **Database**: Minimal writes

### Rate Limiting

The command includes rate limiting to avoid overwhelming itch.io:
- Delays between requests
- Respects itch.io rate limits
- Handles throttling gracefully

## When to Run

### Recommended Schedule

- **Daily**: For active monitoring
- **Weekly**: For regular maintenance
- **Monthly**: Minimum recommended frequency

### Manual Execution

Run manually when:
- Investigating missing games
- After bulk game imports
- Troubleshooting search issues
- Cleaning up database

## Troubleshooting

### Network Errors

**Problem**: Connection timeouts or network errors

**Solutions**:
1. Check internet connectivity
2. Verify itch.io is accessible
3. Increase timeout settings
4. Run during off-peak hours

### Rate Limiting Issues

**Problem**: Getting rate limited by itch.io

**Solutions**:
1. Increase delay between requests
2. Run during off-peak hours
3. Process in smaller batches
4. Contact itch.io if persistent

### False Positives

**Problem**: Games incorrectly marked as suspended

**Solutions**:
1. Verify game is actually suspended on itch.io
2. Check for temporary itch.io issues
3. Re-run command to confirm
4. Manually update if needed

### Incomplete Check

**Problem**: Command stops before checking all games

**Solutions**:
1. Check for errors in output
2. Verify database connectivity
3. Increase PHP timeout
4. Run again (will skip already checked)

## Unsuspending Games

If a game is restored on itch.io, it can be unsuspended:

### Manual Unsuspension

```sql
UPDATE games 
SET is_suspended = false 
WHERE id = ?
```

### Automatic Detection

The next run of `games:refresh` will detect if a previously suspended game is now available and update accordingly.

## Best Practices

1. **Regular Schedule**: Run on a regular schedule (daily or weekly)
2. **Monitor Output**: Review newly suspended games
3. **Investigate Patterns**: Look for mass suspensions (may indicate issues)
4. **Preserve Data**: Don't delete suspended games, just mark them
5. **User Communication**: Notify users if their followed games are suspended

## Related Commands

- [games:refresh](games-refresh.md) - Refresh game information (skips suspended games)
- [feed:process](feed-process.md) - Process feed (skips suspended games)
- [games:import-stats](games-import-stats.md) - Import stats (skips suspended games)

## See Also

- [Games Commands Overview](games-commands-overview.md)
- Game model suspension logic
- Search exclusion filters


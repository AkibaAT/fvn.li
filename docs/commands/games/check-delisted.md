# games:check-delisted

Check visible games for delisted status by detecting robots noindex meta tag.

## Synopsis

```bash
php artisan games:check-delisted
```

## Description

This command checks all visible games in the database to verify if they have been delisted on itch.io. It fetches each game's project page and checks for the presence of a `<meta name="robots" content="noindex">` tag, updating the `is_delisted` flag accordingly.

Delisted games are:
- Hidden from public listings
- Excluded from search results
- Skipped during data refresh operations
- Marked with `is_delisted = true` in the database

This command helps maintain data integrity by identifying games that have been removed from search engines by itch.io.

## Usage Examples

### Basic Usage

Check all visible games for delisted status:

```bash
php artisan games:check-delisted
```

Output:
```
Starting delisted check for games

Checking game: Example Game (ID: 1234)
  -> Game is not delisted
  Waiting 5 seconds for rate limiting...

Checking game: Another Game (ID: 5678)
  -> Game is now DELISTED
  Waiting 5 seconds for rate limiting...
...

=== Summary ===
Games checked: 100
Newly delisted: 3
Errors: 0
```

### Check Specific Game

```bash
php artisan games:check-delisted --game-id=1234
```

### Check Games by Name

```bash
php artisan games:check-delisted --game-name="visual novel"
```

### Check All Games

```bash
php artisan games:check-delisted --all
```

### DDEV Environment

Run in DDEV:

```bash
ddev artisan games:check-delisted --all
```

## Options

| Option | Description |
|--------|-------------|
| `--game-id=` | ID of the specific game to check |
| `--game-name=` | Name (or part of name) of the game(s) to check |
| `--all` | Check all visible games |
| `--sort=id` | Sort games by field (id, name, created_at, updated_at) |
| `--max-retries=3` | Maximum number of retries for rate-limited requests |
| `--retry-cooldown=30` | Base cooldown time in seconds between retries |

## What It Does

### 1. Fetch Visible Games

Retrieves games based on selection criteria (specific game, name pattern, or all visible games).

### 2. Check Each Game

For each game:
1. Fetches the game's itch.io project page
2. Parses HTML to check for `<meta name="robots" content="noindex">` tag
3. Updates `is_delisted` flag based on presence of noindex tag

### 3. Report Results

Displays:
- Total games checked
- Number of newly delisted games
- Any errors encountered

## Delisted Detection

The command detects delisting through the presence of a robots noindex meta tag:

```html
<meta name="robots" content="noindex">
```

or

```html
<meta name="robots" content="noindex, nofollow">
```

This tag is added by itch.io when a game is delisted from search engines but still accessible via direct link.

## Impact on System

### Database Updates

When a game is marked as delisted:

```sql
UPDATE games
SET is_delisted = true,
    updated_at = NOW()
WHERE id = ?
```

### Automatic Exclusions

Delisted games are automatically excluded from:

1. **Search Results**: Not returned in Meilisearch queries
2. **Game Listings**: Hidden from public game lists
3. **Feed Processing**: Skipped during `feed:process`
4. **Refresh Operations**: Excluded from `games:refresh`
5. **Stats Import**: Not processed by `games:import-stats`

### User Impact

- Games in user lists remain but show as delisted
- Ratings and reviews are preserved
- Historical data is maintained
- Game can be unlisted if noindex tag is removed

## Performance

### Execution Time

- **Small dataset** (< 100 games): 5-10 minutes
- **Medium dataset** (100-1,000 games): 30-60 minutes
- **Large dataset** (> 1,000 games): 1-2 hours

Time varies based on:
- Number of games to check
- Network latency to itch.io
- Rate limiting delays (5 seconds between requests)

### Resource Usage

- **Network**: High (fetches each game page)
- **CPU**: Low (mostly I/O bound)
- **Memory**: Low (processes one game at a time)
- **Database**: Minimal writes

### Rate Limiting

The command includes rate limiting to avoid overwhelming itch.io:
- 5-second delay between requests
- Configurable retry behavior
- Handles throttling gracefully

## Troubleshooting

### Network Errors

**Problem**: Connection timeouts or network errors

**Solutions**:
1. Check internet connectivity
2. Verify itch.io is accessible
3. Increase max-retries option
4. Run during off-peak hours

### Rate Limiting Issues

**Problem**: Getting rate limited by itch.io

**Solutions**:
1. Increase retry-cooldown option
2. Run during off-peak hours
3. Process smaller batches using --game-name filter

### False Positives

**Problem**: Games incorrectly marked as delisted

**Solutions**:
1. Verify game page in browser and check for noindex tag
2. Check for temporary itch.io issues
3. Re-run command to confirm
4. Manually update if needed

## Relisting Games

If a game's noindex tag is removed on itch.io:

### Manual Relisting

```sql
UPDATE games
SET is_delisted = false
WHERE id = ?
```

### Automatic Detection

Running the command again will detect if a previously delisted game no longer has the noindex tag and update accordingly.

## Related Commands

- [games:refresh](refresh.md): Refresh game information (skips delisted games)
- [feed:process](../feed/process.md): Process feed (skips delisted games)

## See Also

- [Games Commands Overview](index.md)
- Game model delisted logic
- Search exclusion filters

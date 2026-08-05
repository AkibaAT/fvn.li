# meilisearch:reindex

Reindex content in Meilisearch for maintenance (normal operations use automatic indexing).

## Synopsis

```bash
php artisan meilisearch:reindex [--type=TYPE]
```

## Description

This command performs maintenance reindexing of content in Meilisearch. It's used for:

- Recovering from index corruption
- Applying configuration changes
- Periodic maintenance
- Troubleshooting search issues

**Note**: Normal operations use automatic indexing via Eloquent observers. This command is only needed for maintenance and troubleshooting.

## Options

### `--type=TYPE`

Specify the type of content to reindex. Available types:

- `all` (default): Reindex all content types
- `games`: Reindex only games
- `dialogue`: Reindex only dialogue texts
- `reviews`: Reindex only reviews
- `tags`: Reindex only tags

```bash
# Reindex everything
php artisan meilisearch:reindex

# Reindex only games
php artisan meilisearch:reindex --type=games

# Reindex only dialogue
php artisan meilisearch:reindex --type=dialogue
```

## Usage Examples

### Full Reindex

Reindex all content:

```bash
php artisan meilisearch:reindex
```

Output:
```
Starting Meilisearch maintenance reindex...
Note: Normal operations use automatic indexing via Eloquent observers

Reindexing all content...
Games: 10,234 reindexed
Dialogue: 1,234,567 reindexed
Reviews: 45,678 reindexed
Tags: 456 reindexed

Reindexing completed successfully!
```

### Partial Reindex

Reindex only games:

```bash
php artisan meilisearch:reindex --type=games
```

Output:
```
Starting Meilisearch maintenance reindex...
Note: Normal operations use automatic indexing via Eloquent observers

Reindexing games...
Games: 10,234 reindexed

Reindexing completed successfully!
```

### DDEV Environment

Run reindex in DDEV:

```bash
ddev artisan meilisearch:reindex
```

## When to Use

### Recommended Scenarios

Use `meilisearch:reindex` when:

1. **Search Results Outdated**: Search returns stale or incorrect results
2. **After Configuration Changes**: Modified searchable/filterable fields
3. **Index Corruption**: Meilisearch index appears corrupted
4. **Periodic Maintenance**: Monthly or quarterly maintenance
5. **After Bulk Updates**: Large data migrations or bulk updates
6. **Troubleshooting**: Diagnosing search issues

### Not Needed For

Don't use for normal operations:

- Creating new games (automatic)
- Updating game information (automatic)
- Adding dialogue (automatic)
- Creating reviews (automatic)
- Modifying tags (automatic)

## Performance

### Execution Time

| Content Type | Records | Estimated Time |
|--------------|---------|----------------|
| Games        | 10,000  | 2-3 minutes    |
| Dialogue     | 1M+     | 10-15 minutes  |
| Reviews      | 50,000  | 3-5 minutes    |
| Tags         | 500     | < 1 minute     |
| **All**      | **All** | **15-25 min**  |

### Resource Usage

- **CPU**: High during reindexing
- **Memory**: Moderate (batched processing)
- **Disk I/O**: Moderate
- **Network**: Low (local Meilisearch)

### Optimization

The command uses batched processing to minimize memory usage:

- Games: 500 per batch
- Dialogue: 1,000 per batch
- Reviews: 500 per batch
- Tags: 100 per batch

## How It Works

### 1. Validation

Checks that Meilisearch is accessible and indexes exist.

### 2. Batch Processing

Processes records in batches to avoid memory issues:

```php
Game::chunk(500, function ($games) {
    $games->searchable();
});
```

### 3. Progress Tracking

Displays progress during reindexing:

```
  Reindexing games...
  Processing batch 1/21 (500 records)
  Processing batch 2/21 (500 records)
  ...
  Games: 10,234 reindexed
```

### 4. Verification

Confirms successful completion and provides statistics.

## Automatic Indexing

After reindexing, automatic indexing continues to work:

### Model Observers

Eloquent observers handle automatic indexing:

```php
// Creating a game
$game = Game::create([...]);
// Automatically indexed in Meilisearch

// Updating a game
$game->update(['title' => 'New Title']);
// Automatically re-indexed

// Deleting a game
$game->delete();
// Automatically removed from index
```

### Searchable Models

The following models are automatically indexed:

- `App\Models\Game`
- `App\Models\UniqueDialogueText`
- `App\Models\Review`
- `App\Models\Tag`

## Troubleshooting

### Reindex Fails

**Problem**: Reindexing fails with errors

**Solutions**:
1. Check Meilisearch is running: `ddev describe`
2. Verify database connectivity
3. Check Meilisearch logs: `ddev logs -s meilisearch`
4. Try reindexing specific type: `--type=games`
5. Increase PHP memory limit

### Slow Performance

**Problem**: Reindexing is very slow

**Solutions**:
1. Run during low-traffic periods
2. Reindex specific types instead of all
3. Increase Meilisearch memory allocation
4. Check database query performance
5. Verify network connectivity

### Memory Issues

**Problem**: Out of memory errors

**Solutions**:
1. Increase PHP memory limit in `php.ini`
2. Reduce batch size in command code
3. Reindex one type at a time
4. Restart PHP-FPM: `ddev restart`

### Incomplete Reindex

**Problem**: Some records not reindexed

**Solutions**:
1. Check for errors in output
2. Verify all models are searchable
3. Check database for orphaned records
4. Run again with specific type
5. Check Meilisearch index health

## Related Commands

- [meilisearch:setup](setup.md): Initial Meilisearch setup
- [scout:sync-index-settings](sync-index-settings.md): Sync index configuration

## See Also

- [Meilisearch Commands Overview](index.md)
- [Laravel Scout Documentation](https://laravel.com/docs/scout)
- [Meilisearch Documentation](https://www.meilisearch.com/docs)

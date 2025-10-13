# meilisearch:setup

Set up Meilisearch indexes and migrate data from PostgreSQL search.

## Synopsis

```bash
php artisan meilisearch:setup [--force]
```

## Description

This command performs the initial setup of Meilisearch for FVN.li, including:

1. **Connection Verification**: Checks that Meilisearch is accessible
2. **Index Creation**: Creates all required search indexes
3. **Configuration**: Applies searchable, filterable, and sortable field settings
4. **Data Migration**: Imports existing data from PostgreSQL into Meilisearch
5. **Verification**: Confirms successful setup

This is typically run once during initial deployment or when setting up a new environment.

## Options

### `--force`

Force reindexing even if indexes already exist.

```bash
php artisan meilisearch:setup --force
```

Use this option to:
- Rebuild indexes from scratch
- Recover from corrupted indexes
- Apply configuration changes that require full reindex

## Usage Examples

### Initial Setup

Set up Meilisearch on a new environment:

```bash
php artisan meilisearch:setup
```

### Force Rebuild

Rebuild all indexes from scratch:

```bash
php artisan meilisearch:setup --force
```

### DDEV Environment

Run setup in DDEV:

```bash
ddev artisan meilisearch:setup
```

## What It Does

### 1. Connection Check

Verifies that Meilisearch is running and accessible:

```
🔍 Setting up Meilisearch for FVN.li...
✅ Meilisearch connection successful
```

If connection fails:
```
❌ Cannot connect to Meilisearch. Please ensure it is running.
```

### 2. Index Setup

Creates and configures the following indexes:

#### Games Index
- Searchable: title, description, author
- Filterable: tags, languages, platforms, content_flags, rating, release_date
- Sortable: rating, release_date, created_at

#### Dialogue Index
- Searchable: text, character_name
- Filterable: game_id, language, character_id
- Sortable: created_at

#### Reviews Index
- Searchable: content, title
- Filterable: game_id, rating, user_id
- Sortable: rating, created_at

#### Tags Index
- Searchable: name, description
- Filterable: category, usage_count
- Sortable: usage_count, name

### 3. Data Import

Imports existing data from PostgreSQL:

```
📊 Importing data...
  ✓ Games: 10,234 indexed
  ✓ Dialogue: 1,234,567 indexed
  ✓ Reviews: 45,678 indexed
  ✓ Tags: 456 indexed
```

### 4. Success Message

Displays helpful information after successful setup:

```
🎉 Meilisearch setup completed successfully!

✨ Search indexing is now automatic!
  • New games and dialogue texts are indexed automatically
  • Updates to existing content trigger re-indexing
  • No manual intervention needed for normal operations

💡 Useful commands:
  • Test search: php artisan meilisearch:test "your query"
  • Maintenance reindex: php artisan meilisearch:reindex
  • Check search health: Use SearchIndexService::healthCheck()

🔧 For development testing:
  php artisan tinker
  >>> App\Models\Game::search("your query")->get()
```

## Prerequisites

### Meilisearch Running

Ensure Meilisearch is running before setup:

```bash
# Check DDEV services
ddev describe

# Or check Meilisearch directly
curl http://localhost:7700/health
```

### Environment Configuration

Verify `.env` settings:

```
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=your-master-key
```

### Database Data

Ensure PostgreSQL database is populated with data to import.

## Performance

### Execution Time

- Small dataset (< 1,000 games): 1-2 minutes
- Medium dataset (1,000-10,000 games): 5-10 minutes
- Large dataset (> 10,000 games): 10-20 minutes

### Resource Usage

- **Memory**: Meilisearch uses RAM for indexing
- **CPU**: High during initial import
- **Disk**: Indexes stored on disk after import

### Optimization Tips

1. **Run during low traffic**: Setup can be resource-intensive
2. **Adequate memory**: Ensure Meilisearch has sufficient RAM
3. **Monitor progress**: Watch logs for any errors
4. **Verify completion**: Check that all data was imported

## Troubleshooting

### Connection Failed

**Problem**: Cannot connect to Meilisearch

**Solutions**:
1. Check Meilisearch is running: `ddev describe`
2. Verify `MEILISEARCH_HOST` in `.env`
3. Check Meilisearch logs: `ddev logs -s meilisearch`
4. Restart Meilisearch: `ddev restart`

### Import Timeout

**Problem**: Import times out for large datasets

**Solutions**:
1. Increase PHP timeout in `php.ini`
2. Run in smaller batches using `meilisearch:reindex --type=games`
3. Increase Meilisearch memory allocation
4. Run during off-peak hours

### Partial Import

**Problem**: Some data not imported

**Solutions**:
1. Check for errors in output
2. Verify database connectivity
3. Run with `--force` to retry
4. Check Meilisearch logs for errors

### Index Already Exists

**Problem**: Indexes already exist

**Solutions**:
1. Use `--force` to rebuild: `php artisan meilisearch:setup --force`
2. Or manually delete indexes first
3. Or skip setup if indexes are already configured

## Post-Setup

### Verify Search Works

Test search functionality:

```bash
php artisan tinker
>>> App\Models\Game::search("visual novel")->get()
```

### Check Index Health

Verify indexes are healthy:

```bash
curl http://localhost:7700/indexes
```

### Monitor Performance

Watch search performance in application logs and Meilisearch dashboard.

## Automatic Indexing

After setup, indexing is automatic:

- **New records**: Indexed immediately on creation
- **Updates**: Re-indexed on save
- **Deletions**: Removed from index on delete

No manual intervention needed for normal operations.

## Related Commands

- [meilisearch:reindex](meilisearch-reindex.md) - Reindex content for maintenance
- [scout:sync-index-settings](scout-sync-index-settings.md) - Sync index configuration

## See Also

- [Meilisearch Commands Overview](meilisearch-commands-overview.md)
- [Laravel Scout Documentation](https://laravel.com/docs/scout)
- [Meilisearch Documentation](https://www.meilisearch.com/docs)


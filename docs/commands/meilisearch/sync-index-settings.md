# scout:sync-index-settings

Sync your configured index settings with your search engine (Meilisearch).

## Synopsis

```bash
php artisan scout:sync-index-settings
```

## Description

This command synchronizes the index configuration defined in your Laravel Scout models with Meilisearch. It updates:

- Searchable attributes
- Filterable attributes
- Sortable attributes
- Ranking rules
- Stop words
- Synonyms
- Typo tolerance settings

Use this command after modifying search configuration in your models to apply the changes to Meilisearch.

## Usage Examples

### Basic Usage

Sync all index settings:

```bash
php artisan scout:sync-index-settings
```

Output:
```
Syncing index settings for [games]...
Syncing index settings for [dialogue]...
Syncing index settings for [reviews]...
Syncing index settings for [tags]...
All indexes synced!
```

### DDEV Environment

Run in DDEV:

```bash
ddev artisan scout:sync-index-settings
```

## When to Use

### Required Scenarios

Use `scout:sync-index-settings` when you:

1. **Modify Searchable Fields**: Change which fields are searchable
2. **Update Filterable Fields**: Add or remove filterable attributes
3. **Change Sortable Fields**: Modify which fields can be sorted
4. **Adjust Ranking Rules**: Update search result ranking
5. **Configure Stop Words**: Add or remove stop words
6. **Update Synonyms**: Modify synonym lists
7. **Change Typo Tolerance**: Adjust typo tolerance settings

### After Model Changes

After modifying the `toSearchableArray()` method or Scout configuration in models:

```php
// Before
public function toSearchableArray()
{
    return [
        'id' => $this->id,
        'title' => $this->title,
    ];
}

// After: added description
public function toSearchableArray()
{
    return [
        'id' => $this->id,
        'title' => $this->title,
        'description' => $this->description, // New field
    ];
}

// Run sync to apply changes
// php artisan scout:sync-index-settings
```

## Configuration

### Model Configuration

Index settings are defined in Scout models using the `searchableAs()` and configuration methods:

```php
use Laravel\Scout\Searchable;

class Game extends Model
{
    use Searchable;

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'author' => $this->author,
            'tags' => $this->tags->pluck('name'),
            'languages' => $this->languages->pluck('code'),
            'rating' => $this->rating,
            'release_date' => $this->release_date,
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'games';
    }
}
```

### Meilisearch Settings

Configure Meilisearch-specific settings in `config/scout.php`:

```php
'meilisearch' => [
    'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
    'key' => env('MEILISEARCH_KEY'),
    'index-settings' => [
        'games' => [
            'filterableAttributes' => ['tags', 'languages', 'platforms', 'rating'],
            'sortableAttributes' => ['rating', 'release_date', 'created_at'],
            'rankingRules' => [
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
            ],
        ],
    ],
],
```

## What It Syncs

### Searchable Attributes

Fields that can be searched:

```php
// Defined in toSearchableArray()
'title' => $this->title,
'description' => $this->description,
'author' => $this->author,
```

### Filterable Attributes

Fields that can be used in filters:

```php
'filterableAttributes' => [
    'tags',
    'languages',
    'platforms',
    'content_flags',
    'rating',
    'release_date',
],
```

### Sortable Attributes

Fields that can be used for sorting:

```php
'sortableAttributes' => [
    'rating',
    'release_date',
    'created_at',
    'updated_at',
],
```

### Ranking Rules

Order of ranking criteria:

```php
'rankingRules' => [
    'words',      // Number of matched words
    'typo',       // Typo tolerance
    'proximity',  // Word proximity
    'attribute',  // Attribute ranking
    'sort',       // Custom sort
    'exactness',  // Exact matches
],
```

## Performance Impact

### Sync Time

- **Small indexes** (< 1,000 docs): < 1 second
- **Medium indexes** (1,000-100,000 docs): 1-5 seconds
- **Large indexes** (> 100,000 docs): 5-30 seconds

### No Reindexing

This command only updates settings, not data. It does **not** trigger reindexing.

### Zero Downtime

Settings are updated without downtime. Search continues to work during sync.

## Verification

### Check Settings Applied

Verify settings in Meilisearch:

```bash
# Check games index settings
curl http://localhost:7700/indexes/games/settings
```

### Test Filters

Verify filterable attributes work:

```bash
php artisan tinker
>>> App\Models\Game::search('visual novel')
       ->where('rating', '>', 4.0)
       ->get()
```

### Test Sorting

Verify sortable attributes work:

```bash
php artisan tinker
>>> App\Models\Game::search('visual novel')
       ->orderBy('rating', 'desc')
       ->get()
```

## Troubleshooting

### Settings Not Applied

**Problem**: Settings don't seem to apply

**Solutions**:
1. Check Meilisearch is running
2. Verify configuration in `config/scout.php`
3. Clear config cache: `php artisan config:clear`
4. Check Meilisearch logs for errors
5. Manually verify settings via API

### Filter Not Working

**Problem**: Cannot filter by a field

**Solutions**:
1. Ensure field is in `filterableAttributes`
2. Run `scout:sync-index-settings`
3. Verify field exists in `toSearchableArray()`
4. Check field data type matches filter

### Sort Not Working

**Problem**: Cannot sort by a field

**Solutions**:
1. Ensure field is in `sortableAttributes`
2. Run `scout:sync-index-settings`
3. Verify field exists in `toSearchableArray()`
4. Check field contains sortable data

### Connection Error

**Problem**: Cannot connect to Meilisearch

**Solutions**:
1. Check Meilisearch is running: `ddev describe`
2. Verify `MEILISEARCH_HOST` in `.env`
3. Check network connectivity
4. Restart Meilisearch: `ddev restart`

## Common Scenarios

### Adding a New Filterable Field

1. Add field to model's `toSearchableArray()`
2. Add field to `filterableAttributes` in config
3. Run `scout:sync-index-settings`
4. Test filtering works

### Changing Ranking Rules

1. Modify `rankingRules` in config
2. Run `scout:sync-index-settings`
3. Test search result ordering

### Adding Synonyms

1. Add synonyms to config
2. Run `scout:sync-index-settings`
3. Test synonym search works

## Related Commands

- [meilisearch:setup](setup.md): Initial Meilisearch setup
- [meilisearch:reindex](reindex.md): Reindex content

## See Also

- [Meilisearch Commands Overview](index.md)
- [Laravel Scout Documentation](https://laravel.com/docs/scout)
- [Meilisearch Settings Documentation](https://www.meilisearch.com/docs/reference/api/settings)

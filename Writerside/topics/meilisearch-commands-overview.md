# Meilisearch Commands Overview

Meilisearch commands manage the search indexing system for FVN.li. These commands handle initial setup, maintenance reindexing, and index configuration.

## Available Commands

- **[meilisearch:setup](meilisearch-setup.md)** - Set up Meilisearch indexes and migrate data from PostgreSQL search
- **[meilisearch:reindex](meilisearch-reindex.md)** - Reindex content in Meilisearch for maintenance (normal operations use automatic indexing)
- **[scout:sync-index-settings](scout-sync-index-settings.md)** - Sync your configured index settings with your search engine

## When to Use

### Initial Setup
Use `meilisearch:setup` when:
- Setting up a new environment
- Migrating from PostgreSQL full-text search to Meilisearch
- Rebuilding indexes from scratch

### Maintenance
Use `meilisearch:reindex` when:
- Search results seem outdated or incorrect
- After making changes to index configuration
- Recovering from index corruption
- Performing periodic maintenance

### Configuration Updates
Use `scout:sync-index-settings` when:
- Updating searchable attributes
- Changing filterable or sortable fields
- Modifying ranking rules
- Updating stop words or synonyms

## Automatic Indexing

**Important**: Normal operations use automatic indexing via Eloquent observers. Manual reindexing is only needed for:
- Initial setup
- Maintenance and troubleshooting
- Configuration changes
- Bulk data migrations

The following operations trigger automatic indexing:
- Creating new games
- Updating game information
- Adding or updating dialogue texts
- Creating or updating reviews
- Modifying tags

## Search Indexes

FVN.li maintains the following Meilisearch indexes:

### Games Index
- **Primary Key**: `id`
- **Searchable Fields**: title, description, author
- **Filterable Fields**: tags, languages, platforms, content_flags, rating, release_date
- **Sortable Fields**: rating, release_date, created_at

### Dialogue Index
- **Primary Key**: `id`
- **Searchable Fields**: text, character_name
- **Filterable Fields**: game_id, language, character_id
- **Sortable Fields**: created_at

### Reviews Index
- **Primary Key**: `id`
- **Searchable Fields**: content, title
- **Filterable Fields**: game_id, rating, user_id
- **Sortable Fields**: rating, created_at

### Tags Index
- **Primary Key**: `id`
- **Searchable Fields**: name, description
- **Filterable Fields**: category, usage_count
- **Sortable Fields**: usage_count, name

## Performance Considerations

### Index Size
- Games: ~10,000 documents
- Dialogue: ~1,000,000+ documents
- Reviews: ~50,000 documents
- Tags: ~500 documents

### Reindexing Time
- Full reindex: 5-15 minutes depending on data volume
- Partial reindex: 1-5 minutes
- Individual updates: Near-instant via observers

### Resource Usage
- Meilisearch uses RAM for indexing
- Ensure adequate memory during reindexing
- Consider running during low-traffic periods

## Troubleshooting

### Connection Issues
If you see "Cannot connect to Meilisearch":
1. Check that Meilisearch is running: `ddev describe`
2. Verify the `MEILISEARCH_HOST` in `.env`
3. Check Meilisearch logs: `ddev logs -s meilisearch`

### Slow Search
If search is slow:
1. Check index health
2. Verify filterable/sortable fields are properly configured
3. Consider reducing searchable fields
4. Check Meilisearch resource allocation

### Missing Results
If search results are missing:
1. Run `meilisearch:reindex` to rebuild indexes
2. Check that automatic indexing is working
3. Verify model observers are registered
4. Check Meilisearch logs for errors

## Best Practices

1. **Initial Setup**: Always run `meilisearch:setup` on new environments
2. **Configuration Changes**: Run `scout:sync-index-settings` after modifying search configuration
3. **Maintenance**: Schedule periodic reindexing (monthly or quarterly)
4. **Monitoring**: Monitor search performance and index health
5. **Backups**: Meilisearch data is derived from PostgreSQL, so database backups are sufficient

## Related Documentation

- [Laravel Scout Documentation](https://laravel.com/docs/scout)
- [Meilisearch Documentation](https://www.meilisearch.com/docs)
- SearchIndexService - Service handling search operations


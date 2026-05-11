# games:refresh-feedless

Refreshes version information for games that don't appear in the itch.io feed.

## Overview

This command specifically targets games that are not included in itch.io's public feed but still need regular updates.
These "feedless" games require manual refresh to keep their information current, as they won't be automatically updated
through the normal feed processing.

**Key Features**: Feedless game detection, targeted refresh, manual update control, comprehensive logging.

## Usage

```bash
php artisan games:refresh-feedless [options]
```

## Options

The command supports various filtering and processing options similar to the main refresh command, allowing targeted
updates of feedless games.

## Feedless Game Categories

Several types of games don't appear in the itch.io feed:

### Private/Unlisted Games

- **Developer Testing**: Games in development or testing phases
- **Private Releases**: Games shared with limited audiences
- **Unlisted Content**: Games not publicly discoverable

### Restricted Access Games

- **Age-Restricted**: Adult content with access restrictions
- **Regional Restrictions**: Games limited to specific regions
- **Membership Required**: Games requiring special access

### Legacy Games

- **Archived Games**: Older games no longer actively promoted
- **Discontinued**: Games no longer maintained but still accessible
- **Historical Content**: Games preserved for historical interest

## Examples

<tabs>
<tab title="All Feedless Games">
<code-block lang="bash">
php artisan games:refresh-feedless
</code-block>
<p>Refreshes all games identified as feedless.</p>
</tab>
<tab title="Specific Game">
<code-block lang="bash">
php artisan games:refresh-feedless --game-id=123
</code-block>
<p>Refreshes a specific feedless game by ID.</p>
</tab>
<tab title="Limited Batch">
<code-block lang="bash">
php artisan games:refresh-feedless --limit=25
</code-block>
<p>Processes only the first 25 feedless games.</p>
</tab>
<tab title="Version Updates Only">
<code-block lang="bash">
php artisan games:refresh-feedless --update-version
</code-block>
<p>Only refreshes version information for feedless games.</p>
</tab>
</tabs>

## When to Use

<procedure title="Recommended Usage Scenarios">
<step>Regular scheduled execution to maintain feedless game data</step>
<step>After discovering games are missing from feed updates</step>
<step>When investigating data inconsistencies</step>
<step>During comprehensive database maintenance</step>
<step>After changes to feed processing logic</step>
</procedure>

## Detection Logic

The command identifies feedless games through several criteria:

### Feed Absence

- **Missing from Recent Feeds**: Games not seen in recent feed data
- **Long Update Gaps**: Games with unusually long periods without updates
- **Feed Exclusion Patterns**: Games matching known exclusion criteria

### Manual Flagging

- **Database Flags**: Games manually marked as feedless
- **Category-Based**: Games in categories known to be excluded
- **Developer Requests**: Games excluded at developer request

## Refresh Strategy

Feedless games require special handling:

### API Access

- **Direct API Calls**: Bypasses feed-based discovery
- **Authentication**: May require special API credentials
- **Rate Limiting**: Careful management of API call frequency

### Data Validation

- **Availability Checks**: Verifies games are still accessible
- **Status Updates**: Tracks changes in game availability
- **Error Handling**: Manages games that become inaccessible

## Performance Considerations

<table>
<tr>
    <td>Factor</td>
    <td>Impact</td>
    <td>Mitigation</td>
</tr>
<tr>
    <td>API Rate Limits</td>
    <td>Slower processing</td>
    <td>Batch size management</td>
</tr>
<tr>
    <td>Authentication</td>
    <td>Additional overhead</td>
    <td>Credential caching</td>
</tr>
<tr>
    <td>Error Rates</td>
    <td>Higher failure rates</td>
    <td>Robust retry logic</td>
</tr>
<tr>
    <td>Data Completeness</td>
    <td>Partial information</td>
    <td>Graceful degradation</td>
</tr>
</table>

## Monitoring

Track these metrics for feedless game processing:

- **Games Processed**: Number of feedless games updated
- **Success Rate**: Percentage of successful updates
- **API Errors**: Failed requests requiring attention
- **Data Quality**: Completeness of retrieved information
- **Processing Time**: Time required for complete refresh

## Error Handling

Feedless games often have higher error rates:

### Access Issues

- **Authentication Failures**: Invalid or expired credentials
- **Permission Denied**: Insufficient access rights
- **Game Unavailable**: Games that have been removed or restricted

### Data Issues

- **Incomplete Information**: Missing or partial game data
- **Format Changes**: Unexpected data structure changes
- **Validation Failures**: Data that doesn't meet quality standards

## Integration

Feedless refresh integrates with other systems:

- **Feed Processing**: Complements normal feed-based updates
- **Manual Refresh**: Can be triggered for specific games
- **Monitoring Systems**: Reports on data freshness and quality
- **Admin Interface**: Provides visibility into feedless game status

## Maintenance

Regular maintenance ensures effective feedless game management:

- **Review Feedless List**: Periodically verify which games are truly feedless
- **Update Detection Logic**: Refine criteria for identifying feedless games
- **Monitor Success Rates**: Track and improve refresh success rates
- **Optimize Performance**: Adjust batch sizes and timing for efficiency

> **Note**: Feedless games may have limited data availability compared to games in the public feed, requiring graceful
> handling of missing information.

## Related Commands

- [games:refresh](games-refresh.md) - Main game refresh command
- [feed:process](feed-process.md) - Process games from public feed
- [games:update-watchlist](games-update-watchlist.md) - Update followed games

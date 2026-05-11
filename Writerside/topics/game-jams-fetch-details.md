# game-jams:fetch-details

Fetches additional details for game jams from itch.io, including participant information, submission counts, and
results.

## Overview

This command enriches game jam data by fetching comprehensive details from itch.io. By default, it processes game jams
marked as needing details fetch, but can be configured to process specific jams or all jams.

**Default behavior**: Processes game jams with `needs_details_fetch=true`.

## Usage

```bash
php artisan game-jams:fetch-details [options]
```

## Options

<deflist>
<def title="--all">
Fetch details for all game jams, not just those marked as needing details.
</def>
<def title="--id=ID">
ID of the specific game jam to process.
</def>
<def title="--name=NAME">
Name (or part of name) of the game jam(s) to process.
</def>
<def title="--url=URL">
URL of the specific game jam to process.
</def>
<def title="--limit=LIMIT">
Limit the number of game jams to process (default: 10).
</def>
<def title="--results">
Force fetching of results pages even for ongoing jams.
</def>
<def title="--max-retries=COUNT">
Maximum number of retries for rate-limited requests (default: 3).
</def>
<def title="--retry-cooldown=SECONDS">
Base cooldown time in seconds between retries (default: 30).
</def>
</deflist>

## Data Fetched

The command retrieves comprehensive game jam information:

- **Basic Information**: Title, description, dates, theme
- **Participation Data**: Number of participants and submissions
- **Rules and Guidelines**: Jam rules, submission requirements
- **Results**: Rankings and winners (when available)
- **Associated Games**: Games submitted to the jam
- **Creator Information**: Jam organizers and participants

## Examples

<tabs>
<tab title="Default Processing">
<code-block lang="bash">
php artisan game-jams:fetch-details
</code-block>
<p>Processes up to 10 game jams marked as needing details fetch.</p>
</tab>
<tab title="Specific Game Jam">
<code-block lang="bash">
php artisan game-jams:fetch-details --id=12345
</code-block>
<p>Fetches details for a specific game jam by ID.</p>
</tab>
<tab title="By Name">
<code-block lang="bash">
php artisan game-jams:fetch-details --name="Ludum Dare"
</code-block>
<p>Processes all game jams with "Ludum Dare" in the name.</p>
</tab>
<tab title="All Jams (Limited)">
<code-block lang="bash">
php artisan game-jams:fetch-details --all --limit=50
</code-block>
<p>Processes up to 50 game jams regardless of their needs_details_fetch status.</p>
</tab>
<tab title="Force Results">
<code-block lang="bash">
php artisan game-jams:fetch-details --id=12345 --results
</code-block>
<p>Fetches results even for ongoing jams (may return empty results).</p>
</tab>
</tabs>

## When to Use

<procedure title="Recommended Usage Scenarios">
<step>After discovering new game jams through feed processing</step>
<step>When game jam information appears incomplete</step>
<step>To update results after jam completion</step>
<step>For comprehensive data collection on specific jams</step>
<step>During initial database population</step>
</procedure>

## Processing Modes

<table>
<tr>
    <td>Mode</td>
    <td>Description</td>
    <td>Use Case</td>
</tr>
<tr>
    <td>Default</td>
    <td>Jams needing details</td>
    <td>Regular maintenance</td>
</tr>
<tr>
    <td>Specific ID</td>
    <td>Single jam by ID</td>
    <td>Targeted updates</td>
</tr>
<tr>
    <td>By Name</td>
    <td>Jams matching name pattern</td>
    <td>Series or themed jams</td>
</tr>
<tr>
    <td>All Jams</td>
    <td>Every jam in database</td>
    <td>Full refresh or migration</td>
</tr>
</table>

## Rate Limiting

The command includes sophisticated rate limiting handling:

- **Automatic Retries**: Configurable retry count for 429 responses
- **Exponential Backoff**: Increasing cooldown between retries
- **Batch Processing**: Respects API limits with configurable batch sizes
- **Progress Tracking**: Continues from where it left off after rate limits

## Error Handling

Comprehensive error handling includes:

- **Network Failures**: Automatic retry for temporary issues
- **Invalid URLs**: Graceful handling of malformed jam URLs
- **Missing Data**: Continues processing when some fields are unavailable
- **API Changes**: Robust parsing that adapts to itch.io structure changes

> **Warning**: Using `--all` with a large database may take considerable time and should be run during maintenance
> windows.

## Related Commands

- [games:refresh](games-refresh.md) - Refresh games associated with jams
- [feed:process](feed-process.md) - Discover new game jams

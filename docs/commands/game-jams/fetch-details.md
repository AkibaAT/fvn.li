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

--all
:   Fetch details for all game jams, not just those marked as needing details.

--id=ID
:   ID of the specific game jam to process.

--name=NAME
:   Name (or part of name) of the game jam(s) to process.

--url=URL
:   URL of the specific game jam to process.

--limit=LIMIT
:   Limit the number of game jams to process (default: 10).

--results
:   Force fetching of results pages even for ongoing jams.

--max-retries=COUNT
:   Maximum number of retries for rate-limited requests (default: 3).

--retry-cooldown=SECONDS
:   Base cooldown time in seconds between retries (default: 30).

## Data Fetched

The command retrieves comprehensive game jam information:

- **Basic Information**: Title, description, dates, theme
- **Participation Data**: Number of participants and submissions
- **Rules and Guidelines**: Jam rules, submission requirements
- **Results**: Rankings and winners (when available)
- **Associated Games**: Games submitted to the jam
- **Creator Information**: Jam organizers and participants

## Examples

=== "Default Processing"

    ```bash
    php artisan game-jams:fetch-details
    ```
    <p>Processes up to 10 game jams marked as needing details fetch.</p>

=== "Specific Game Jam"

    ```bash
    php artisan game-jams:fetch-details --id=12345
    ```
    <p>Fetches details for a specific game jam by ID.</p>

=== "By Name"

    ```bash
    php artisan game-jams:fetch-details --name="Ludum Dare"
    ```
    <p>Processes all game jams with "Ludum Dare" in the name.</p>

=== "All Jams (Limited)"

    ```bash
    php artisan game-jams:fetch-details --all --limit=50
    ```
    <p>Processes up to 50 game jams regardless of their needs_details_fetch status.</p>

=== "Force Results"

    ```bash
    php artisan game-jams:fetch-details --id=12345 --results
    ```
    <p>Fetches results even for ongoing jams (may return empty results).</p>

## When to Use

**Recommended Usage Scenarios**

1. After discovering new game jams through feed processing
2. When game jam information appears incomplete
3. To update results after jam completion
4. For comprehensive data collection on specific jams
5. During initial database population

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

## Related Commands

- [games:refresh](../games/refresh.md): Refresh games associated with jams
- [feed:process](../feed/process.md): Discover new game jams

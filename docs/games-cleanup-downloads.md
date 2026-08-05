# games:cleanup-downloads

Cleans up old game version downloads, keeping only the latest version to save storage space.

## Overview

This command removes outdated game download files from storage while preserving the most recent version of each game. It
helps manage disk space by removing files that are no longer needed for analysis or processing.

## Usage

```bash
php artisan games:cleanup-downloads [options]
```

## Options

--game-id=ID
:   ID of the specific game to clean up.

--game-name=NAME
:   Name (or part of name) of the game(s) to clean up.

--all
:   Clean up downloads for all games.

## Cleanup Logic

The command follows this cleanup process:

1. **Identifies game versions** for the specified scope
2. **Determines latest version** for each game
3. **Marks older versions** for cleanup
4. **Removes download files** while preserving metadata
5. **Updates database records** to reflect cleanup status
6. **Reports storage savings** achieved

## Examples

=== "Specific Game"

    ```bash
    php artisan games:cleanup-downloads --game-id=123
    ```
    <p>Cleans up old downloads for game ID 123, keeping only the latest version.</p>

=== "By Name"

    ```bash
    php artisan games:cleanup-downloads --game-name="Doki Doki"
    ```
    <p>Cleans up downloads for all games with "Doki Doki" in the name.</p>

=== "All Games"

    ```bash
    php artisan games:cleanup-downloads --all
    ```
    <p>Cleans up old downloads for all games in the database.</p>

=== "Verbose Output"

    ```bash
    php artisan games:cleanup-downloads --all -v
    ```
    <p>Shows detailed information about each file being removed.</p>

## When to Use

**Recommended Usage Scenarios**

1. Regular maintenance to manage storage space
2. Before storage capacity reaches limits
3. After bulk game imports or updates
4. When preparing for system backups
5. During server maintenance windows

## Safety Features

The command includes several safety mechanisms:

### Data Preservation

- **Metadata Retention**: Database records remain intact
- **Latest Version Protection**: Never removes the most recent version
- **Analysis Data**: Preserves processed statistics and character data

### Verification Checks

- **File Existence**: Verifies files exist before attempting removal
- **Permission Checks**: Ensures write access to storage directories
- **Database Consistency**: Maintains referential integrity

## Storage Impact

<table>
<tr>
    <td>File Type</td>
    <td>Typical Size</td>
    <td>Cleanup Impact</td>
</tr>
<tr>
    <td>Game Archives</td>
    <td>50-500 MB</td>
    <td>High storage savings</td>
</tr>
<tr>
    <td>Extracted Files</td>
    <td>100-1000 MB</td>
    <td>Very high savings</td>
</tr>
<tr>
    <td>Processing Temp</td>
    <td>10-100 MB</td>
    <td>Medium savings</td>
</tr>
<tr>
    <td>Metadata</td>
    <td><![CDATA[< 1 MB]]></td>
    <td>Preserved (not cleaned)</td>
</tr>
</table>

## Cleanup Scope

The command can target different scopes:

### Single Game (`--game-id`)

- Fastest execution
- Precise control
- Minimal system impact

### Named Games (`--game-name`)

- Pattern-based selection
- Useful for series or collections
- Moderate system impact

### All Games (`--all`)

- Maximum storage savings
- Longest execution time
- Highest system impact

## Recovery

If files need to be restored after cleanup:

1. **Re-download** from original sources (if available)
2. **Restore from backups** (if backup strategy includes downloads)
3. **Re-import** using other commands if source files exist

> **Warning**: Cleanup is irreversible. Ensure you have alternative access to game files if needed for future analysis.

## Related Commands

- [games:refresh](games-refresh.md) - Re-download game files
- [games:reimport-version](games-reimport-version.md) - Reprocess existing files

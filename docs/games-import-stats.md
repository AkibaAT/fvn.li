# games:import-stats

Imports statistics JSON data for a specific game version.

## Overview

This command imports pre-generated statistics from JSON files into the database for a specific game version. It's
typically used to import analysis results from external processing or to restore statistics data.

## Usage

```bash
php artisan games:import-stats [options]
```

## Options

--game-id=ID
:   ID of the specific game to process.

--game-name=NAME
:   Name of the game to process (only used if game-id is not provided).

--version-id=ID
:   Game version ID in the database.

--stats-file=PATH
:   Path to the stats JSON file to import.

## JSON Format

The stats JSON file should contain structured data with the following format:

```json
{
  "characters": [
    {
      "name": "Character Name",
      "blocks": 150,
      "words": 2500,
      "language": "en"
    }
  ],
  "metadata": {
    "total_blocks": 500,
    "total_words": 8000,
    "languages": ["en", "ja"]
  }
}
```

## Examples

=== "By Game ID"

    ```bash
    php artisan games:import-stats --game-id=123 --stats-file=/path/to/stats.json
    ```
    <p>Imports stats for the latest version of game ID 123.</p>

=== "By Game Name"

    ```bash
    php artisan games:import-stats --game-name="My Game" --stats-file=stats.json
    ```
    <p>Imports stats for the latest version of the named game.</p>

=== "Specific Version"

    ```bash
    php artisan games:import-stats --version-id=456 --stats-file=./data/stats.json
    ```
    <p>Imports stats for a specific version ID.</p>

=== "Relative Path"

    ```bash
    php artisan games:import-stats --game-id=123 --stats-file=storage/stats/game123.json
    ```
    <p>Uses a relative path to the stats file.</p>

## When to Use

**Recommended Usage Scenarios**

1. Importing analysis results from external tools
2. Restoring statistics after data corruption
3. Bulk importing pre-calculated statistics
4. Migrating statistics from other systems
5. Testing with known good statistics data

## Validation

The command performs comprehensive validation:

### File Validation

- **File Existence**: Verifies the stats file exists and is readable
- **JSON Syntax**: Validates proper JSON formatting
- **Schema Validation**: Ensures required fields are present

### Data Validation

- **Character Data**: Validates character names and statistics
- **Numeric Values**: Ensures blocks and words are non-negative integers
- **Language Codes**: Validates language identifiers
- **Consistency**: Checks totals match individual character sums

## Import Process

<table>
<tr>
    <td>Step</td>
    <td>Action</td>
    <td>Validation</td>
</tr>
<tr>
    <td>1. File Loading</td>
    <td>Read and parse JSON file</td>
    <td>File exists, valid JSON</td>
</tr>
<tr>
    <td>2. Target Resolution</td>
    <td>Identify game/version</td>
    <td>Game/version exists</td>
</tr>
<tr>
    <td>3. Data Validation</td>
    <td>Validate statistics data</td>
    <td>Schema compliance</td>
</tr>
<tr>
    <td>4. Database Update</td>
    <td>Import statistics</td>
    <td>Data integrity</td>
</tr>
</table>

## Data Integrity

Import operations maintain data integrity through:

- **Transaction Wrapping**: All-or-nothing import process
- **Backup Validation**: Optionally preserve existing data
- **Consistency Checks**: Verify totals and relationships
- **Duplicate Prevention**: Handle existing statistics appropriately

> **Note**: The import process will overwrite existing statistics for the specified version. Ensure you have backups if
> needed.

## Related Commands

- [games:reimport-version](games-reimport-version.md) - Reimport from stored archives
- [fix:characters](fix-characters.md) - Comprehensive character fixes
- [games:refresh](games-refresh.md) - Refresh game information

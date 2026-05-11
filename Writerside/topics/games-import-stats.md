# games:import-stats

Imports statistics JSON data for a specific game version.

## Overview

This command imports pre-generated statistics from JSON files into the database for a specific game version. It's
typically used to import analysis results from external processing or to restore statistics data.

**Key Features**: JSON validation, data integrity checks, flexible targeting options.

## Usage

```bash
php artisan games:import-stats [options]
```

## Options

<deflist>
<def title="--game-id=ID">
ID of the specific game to process.
</def>
<def title="--game-name=NAME">
Name of the game to process (only used if game-id is not provided).
</def>
<def title="--version-id=ID">
Game version ID in the database.
</def>
<def title="--stats-file=PATH">
Path to the stats JSON file to import.
</def>
</deflist>

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

<tabs>
<tab title="By Game ID">
<code-block lang="bash">
php artisan games:import-stats --game-id=123 --stats-file=/path/to/stats.json
</code-block>
<p>Imports stats for the latest version of game ID 123.</p>
</tab>
<tab title="By Game Name">
<code-block lang="bash">
php artisan games:import-stats --game-name="My Game" --stats-file=stats.json
</code-block>
<p>Imports stats for the latest version of the named game.</p>
</tab>
<tab title="Specific Version">
<code-block lang="bash">
php artisan games:import-stats --version-id=456 --stats-file=./data/stats.json
</code-block>
<p>Imports stats for a specific version ID.</p>
</tab>
<tab title="Relative Path">
<code-block lang="bash">
php artisan games:import-stats --game-id=123 --stats-file=storage/stats/game123.json
</code-block>
<p>Uses a relative path to the stats file.</p>
</tab>
</tabs>

## When to Use

<procedure title="Recommended Usage Scenarios">
<step>Importing analysis results from external tools</step>
<step>Restoring statistics after data corruption</step>
<step>Bulk importing pre-calculated statistics</step>
<step>Migrating statistics from other systems</step>
<step>Testing with known good statistics data</step>
</procedure>

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

## Error Handling

The command handles various error conditions:

- **File Not Found**: Clear error message with file path
- **Invalid JSON**: Detailed parsing error information
- **Missing Game/Version**: Helpful suggestions for resolution
- **Data Validation Errors**: Specific field-level error details
- **Database Errors**: Transaction rollback on failure

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

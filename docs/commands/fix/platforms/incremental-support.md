# fix:platforms:incremental-support

Reports platform support inconsistencies across game versions to ensure platforms are only gained, not lost.

## Overview

This command analyzes platform support across game versions to ensure consistency. It identifies cases where newer
versions lose platform support that was available in previous versions.

> **Important**: This is a **REPORT-ONLY** command that doesn't make any changes to the database.

## Platform Logic

The command enforces the principle that **platform support should be incremental**:

- Once a platform is supported in any version, it should remain supported in all subsequent versions
- Newer versions can gain additional platform support
- Newer versions should never lose platform support from previous versions

## Platforms Checked

<table>
<tr>
    <td>Platform</td>
    <td>Database Field</td>
    <td>Description</td>
</tr>
<tr>
    <td>Windows</td>
    <td>is_windows</td>
    <td>Windows desktop support</td>
</tr>
<tr>
    <td>Linux</td>
    <td>is_linux</td>
    <td>Linux desktop support</td>
</tr>
<tr>
    <td>Mac</td>
    <td>is_mac</td>
    <td>macOS desktop support</td>
</tr>
<tr>
    <td>Android</td>
    <td>is_android</td>
    <td>Android mobile support</td>
</tr>
<tr>
    <td>Web</td>
    <td>is_web</td>
    <td>Web browser support</td>
</tr>
</table>

## Usage

```bash
php artisan fix:platforms:incremental-support [options]
```

## Options

--game-id=ID
:   Report only a specific game by ID. Useful for investigating specific games.

## Examples

=== "Check All Games"

    ```bash
    php artisan fix:platforms:incremental-support
    ```
    <p>Analyzes platform support consistency for all games with multiple versions.</p>

=== "Check Specific Game"

    ```bash
    php artisan fix:platforms:incremental-support --game-id=123
    ```
    <p>Analyzes platform support only for game ID 123.</p>

## Output Format

The command generates a detailed table for each game with issues:

```
Game: Example Visual Novel (ID: 123)
┌─────────┬────────┬─────┬───────┬─────┬─────────┬─────┬───────────┐
│ Version │ Latest │ Win │ Linux │ Mac │ Android │ Web │ Missing   │
├─────────┼────────┼─────┼───────┼─────┼─────────┼─────┼───────────┤
│ 1.0     │        │ Yes │ Yes   │     │         │     │           │
│ 1.1     │        │ Yes │ Yes   │ Yes │         │     │           │
│ 1.2     │ Latest │ Yes │ Missing │ Yes │ Yes     │     │ Linux     │
└─────────┴────────┴─────┴───────┴─────┴─────────┴─────┴───────────┘
```

### Legend

- **Yes**: Platform supported
- **Missing**: Platform missing (should be supported based on previous versions)
- **Latest**: Indicates the current latest version
- **Missing**: Lists platforms that should be supported but aren't

## When to Use

**Recommended Usage Scenarios**

1. Before releasing new game versions
2. During platform support audits
3. When investigating platform availability issues
4. As part of quality assurance processes
5. After bulk updates to game version data

## Understanding Results

### No Issues Found

```
Analyzing platform support consistency across game versions...
No games found to analyze.
```

or

```
Processed 15 games, 0 games with platform support issues found.
```

### Issues Found

```
Analyzing platform support consistency across game versions...

Game: Example VN (ID: 123)
[Table showing version inconsistencies]

Game: Another Game (ID: 456)
[Table showing version inconsistencies]

Summary:
Processed 25 games, 2 games with platform support issues found.
```

## Troubleshooting Platform Issues

When the command identifies inconsistencies:

1. **Review the output** to understand which platforms are missing in which versions
2. **Check the game data** to verify if the platform support is actually incorrect
3. **Update game version records** manually if the data is wrong
4. **Investigate the cause**: Was platform support accidentally removed during updates?

> **Note**: This command only reports issues. Any fixes must be made manually by updating the game version records in
> the database or admin interface.

## Related Commands

This command is standalone but complements the character fix commands by ensuring overall data consistency across the
platform.

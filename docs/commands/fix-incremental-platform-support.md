# Platform Support Consistency Report Command

## Overview

The `fix:platform-support:incremental` command analyzes and reports platform support inconsistencies across game versions. This command identifies cases where platform support decreases between versions, which violates the principle that platforms should only be gained, not lost.

## Usage

```bash
php artisan fix:platform-support:incremental [options]
```

## Options

- `--game-id=ID` - Analyze only a specific game by ID
- `-v, --verbose` - Show detailed output during analysis

## What it does

This is a **report-only** command that:

- **Analyzes all game versions** to identify platform support inconsistencies
- **Displays detailed tables** showing platform support across versions for games with issues
- **Highlights missing platforms** that should be supported based on previous versions
- **Provides clear visual indicators** using colors and symbols
- **Never modifies any data** - purely for analysis and reporting

## Examples

### Analyze all games for platform inconsistencies
```bash
php artisan fix:platform-support:incremental
```

### Analyze all games with verbose output
```bash
php artisan fix:platform-support:incremental -v
```

### Analyze a specific game
```bash
php artisan fix:platform-support:incremental --game-id=123
```

## Output Format

### Game Tables

For each game with platform inconsistencies, the command displays a detailed table:

```
Game: Example Game (ID: 123)
+---------+--------+-----+-------+-----+---------+-----+---------+
| Version | Latest | Win | Linux | Mac | Android | Web | Issues  |
+---------+--------+-----+-------+-----+---------+-----+---------+
| 1.0     |        | ✓   |       |     |         |     |         |
| 2.0     |        | ✓   | ✓     | ✓   |         |     |         |
| 3.0     |        | ✓   | ✗     | ✗   |         |     | Linux, Mac |
| 4.0     | ✓      | ✓   | ✓     | ✓   | ✓       |     |         |
+---------+--------+-----+-------+-----+---------+-----+---------+
```

### Summary Report

The command provides a summary including:

- **Games analyzed** - Total number of games checked
- **Games with platform issues** - Number of games that have inconsistencies
- **Legend** - Explanation of symbols used

## Visual Indicators

- **✓ (Green)** - Platform is supported
- **✗ (Red)** - Platform should be supported but is missing (inconsistency)
- **(Empty)** - Platform is not supported (normal)
- **✓ in Latest column** - Indicates the current/latest version

## Use Cases

1. **Quality assurance** - Identify platform support inconsistencies
2. **Data validation** - Verify platform support data integrity
3. **Release planning** - Understand platform support evolution
4. **Issue investigation** - Debug platform support problems

## Best Practices

1. **Run regularly** as part of data quality checks
2. **Use --game-id** when investigating specific games
3. **Review all reported issues** for data accuracy
4. **Document findings** for development team review
5. **Use as input for data correction** processes

## Understanding the Output

The command follows the principle that **platform support should only increase over time**. When a platform is supported in one version, all subsequent versions should also support that platform.

**Example scenario:**
- Version 1.0: Windows only
- Version 2.0: Windows + Linux (✓ correct - added Linux)
- Version 3.0: Windows only (✗ incorrect - lost Linux support)
- Version 4.0: Windows + Linux + Mac (✓ correct - restored Linux, added Mac)

The command will flag version 3.0 as having an inconsistency because it lost Linux support that was present in version 2.0.

# fix:characters

Comprehensive character data fixes that run in the correct dependency order to ensure data consistency.

## Overview

This command performs all character maintenance tasks in a single, logical workflow:

1. **NULL Assignments**: Assigns dialogue lines with NULL character_id to narrator characters
2. **Special Assignments**: Fixes 'extend' and 'centered' character assignments to previous line's character
3. **Statistics**: Recalculates character and language statistics from dialogue lines
4. **Version References**: Updates first_seen/last_seen versions, creates missing stats, deletes orphaned characters

**Data Completeness Protection**: Only versions with full dialogue line details will have statistics updated. This
protects versions with incomplete data (e.g., Godot games with language totals only).

### Data Completeness Levels

The system recognizes four levels of data completeness:

- **Full Detail**: Has individual dialogue lines with text content - **Safe to update**
- **Character Stats**: Has character-level statistics but no individual lines - **Protected from updates**
- **Language Only**: Has only language-level totals - **Protected from updates**
- **No Detail**: Has only basic version info - **Protected from updates**

Only versions with "Full Detail" level will have their character statistics recalculated.

## Usage

### Basic Usage

```bash
# Fix all character issues for all games
php artisan fix:characters

# Fix all issues for specific game
php artisan fix:characters --game-id=138

# Preview all changes without making them
php artisan fix:characters --dry-run
```

### Step-by-Step Execution

```bash
# Run only NULL assignment fixes
php artisan fix:characters --step=null-assignments

# Run only special character assignment fixes
php artisan fix:characters --step=special-assignments

# Run only statistics recalculation
php artisan fix:characters --step=stats

# Run only version reference updates and cleanup
php artisan fix:characters --step=version-references
```

### Targeted Processing

```bash
# Fix specific version's statistics
php artisan fix:characters --step=stats --version-id=1734

# Fix only 'extend' character assignments
php artisan fix:characters --step=special-assignments --character=extend

# Preview changes for specific game
php artisan fix:characters --game-id=138 --dry-run
```

## Options

| Option             | Description                                                                                 |
|--------------------|---------------------------------------------------------------------------------------------|
| `--game-id=ID`     | Process only the specified game                                                             |
| `--version-id=ID`  | Process only the specified version (overrides --game-id)                                    |
| `--step=STEP`      | Run only a specific step (null-assignments\|special-assignments\|stats\|version-references) |
| `--dry-run`        | Show what would be done without making changes                                              |
| `--character=NAME` | Process only a specific special character (extend, centered)                                |

## Detailed Process

### Step 1: NULL Assignments

**Purpose**: Assign dialogue lines with NULL character_id to narrator characters

**Actions**:

- Finds all dialogue lines with NULL character_id
- Creates narrator characters for games that don't have them
- Assigns all NULL lines to the appropriate narrator character
- Reports lines updated and narrator characters created

**When to use**: Always run first, or when you notice dialogue lines without character assignments

### Step 2: Special Assignments

**Purpose**: Fix special character assignments that should belong to previous line's character

**Actions**:

- Processes 'extend' and 'centered' characters
- Finds the previous dialogue line in the same file
- Reassigns special character lines to the previous line's character
- Reports lines reassigned and versions processed

**Special Characters Handled**:

- `extend` - Continuation of previous character's dialogue
- `centered` - Centered text that should belong to previous character

### Step 3: Statistics

**Purpose**: Recalculate character and language statistics from dialogue lines

**Actions**:

- Recalculates blocks and words for characters with issues (narrator + zero stats)
- Uses sophisticated SQL that matches Python's text.split() behavior
- Automatically recalculates language totals to ensure consistency
- Respects data completeness protection

**Language Totals**: When character statistics are recalculated, the corresponding language-level totals are
automatically updated to ensure consistency between character breakdowns and language totals.

### Step 4: Version References

**Purpose**: Update version tracking and cleanup orphaned data

**Actions**:

- Updates first_seen_in_version_id and last_seen_in_version_id for characters
- Creates missing version_character_stats entries for characters in dialogue
- Deletes orphaned characters that have no dialogue lines
- Reports characters updated, stats created, and characters deleted

## Output Example

```
Starting comprehensive character fixes...
Processing only game ID: 138
Data completeness protection: Only versions with full dialogue line details will be updated

=== Step 1: Fixing NULL Character Assignments ===
Fixed 115765 NULL character assignments
Created 0 narrator characters
Processed 1 games

=== Step 2: Fixing Special Character Assignments ===
Reassigned 42 special character lines
Processed 8 versions
Handled 2 special character types

=== Step 3: Recalculating Character Statistics ===
Updated 156 character statistics
Processed 12 versions

=== Step 4: Fixing Version References and Cleanup ===
Updated 144 character version references
Created 89 missing stats entries
Deleted 319 orphaned characters
Processed 205 characters total

=== Overall Summary ===
Successfully processed:
  • 115807 dialogue line assignments fixed
  • 156 character statistics recalculated
  • 144 character version references updated
  • 319 orphaned characters cleaned up

Character fixes completed successfully!
```

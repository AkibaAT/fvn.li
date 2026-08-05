# games:reimport-version

Reimports version statistics from stored game archives.

## Overview

This command reprocesses stored game archives to regenerate version statistics and character data. It's useful when
analysis algorithms have been improved or when data corruption requires regeneration from original source files.

## Usage

```bash
php artisan games:reimport-version [options]
```

## Options

The command supports various targeting options to control which versions are reimported and how the processing is
performed.

## Reimport Process

The command follows this comprehensive workflow:

1. **Identifies target versions** based on specified criteria
2. **Locates stored archives** for each version
3. **Extracts game files** from archives
4. **Reanalyzes game content** using current algorithms
5. **Regenerates statistics** and character data
6. **Validates new data** against quality standards
7. **Updates database records** with fresh analysis results
8. **Cleans up temporary files** used during processing

## Examples

=== "Specific Version"

    ```bash
    php artisan games:reimport-version --version-id=456
    ```
    <p>Reimports statistics for a specific version ID.</p>

=== "Game Versions"

    ```bash
    php artisan games:reimport-version --game-id=123
    ```
    <p>Reimports all versions for a specific game.</p>

=== "Recent Versions"

    ```bash
    php artisan games:reimport-version --days=30
    ```
    <p>Reimports versions created in the last 30 days.</p>

=== "Force Reimport"

    ```bash
    php artisan games:reimport-version --game-id=123 --force
    ```
    <p>Forces reimport even if statistics appear current.</p>

## When to Use

**Recommended Usage Scenarios**

1. After improving analysis algorithms or character detection
2. When statistics appear incorrect or incomplete
3. After data corruption or database issues
4. During migration to new analysis systems
5. For quality assurance and data validation

## Archive Requirements

Successful reimport requires:

### Archive Availability

- **Stored Files**: Original game archives must be preserved
- **File Integrity**: Archives must be uncorrupted and complete
- **Access Permissions**: System must have read access to archive storage

### Archive Format

- **Supported Formats**: ZIP, RAR, 7Z, and other common archive formats
- **File Structure**: Recognizable game file organization
- **Metadata**: Sufficient information to identify game content

## Processing Capabilities

The reimport process can regenerate various types of data:

### Character Statistics

- **Dialogue Counts**: Blocks and words per character
- **Language Analysis**: Statistics by language
- **Character Relationships**: Interaction patterns and frequencies

### Game Metadata

- **File Analysis**: Game structure and organization
- **Asset Inventory**: Images, audio, and other media files
- **Technical Details**: Engine version, platform support

### Quality Metrics

- **Data Completeness**: Coverage of game content
- **Analysis Confidence**: Reliability of extracted data
- **Validation Results**: Consistency checks and error detection

## Data Validation

The reimport process includes comprehensive validation:

### Source Validation

- **Archive Integrity**: Verifies archives are not corrupted
- **File Completeness**: Ensures all necessary files are present
- **Format Recognition**: Confirms game engine and format compatibility

### Analysis Validation

- **Statistics Consistency**: Checks for reasonable statistical values
- **Character Data**: Validates character names and dialogue assignments
- **Language Detection**: Verifies language identification accuracy

### Output Validation

- **Database Constraints**: Ensures data meets schema requirements
- **Referential Integrity**: Maintains proper relationships between records
- **Quality Thresholds**: Meets minimum quality standards for import

## Comparison and Reporting

The command provides detailed reporting on changes:

### Before/After Comparison

- **Statistics Changes**: Differences in character counts and statistics
- **Data Quality**: Improvements in data completeness and accuracy
- **New Discoveries**: Previously undetected characters or content

### Import Summary

- **Processing Statistics**: Number of versions processed successfully
- **Error Summary**: Types and frequencies of errors encountered
- **Performance Metrics**: Processing time and resource usage

## Recovery and Rollback

The system supports recovery from failed reimports:

- **Transaction Safety**: Database changes are wrapped in transactions
- **Backup Preservation**: Original data is preserved during reimport
- **Rollback Capability**: Can revert to previous data if needed
- **Audit Trail**: Maintains records of all reimport operations

> **Warning**: Reimporting can be time and resource intensive. Test with small batches before processing large numbers
> of versions.

## Related Commands

- [games:import-stats](import-stats.md): Import statistics from JSON files
- [fix:characters](../fix/characters.md): Comprehensive character fixes
- [games:refresh](refresh.md): Refresh game information from itch.io

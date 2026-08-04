# ratings:backfill

Backfills missing ratings by scanning all events to recover historical rating data.

## Overview

This command scans through historical events to identify and import ratings that may have been missed during normal
import processes. It's designed to fill gaps in rating data and ensure comprehensive coverage of all available ratings.

## Usage

```bash
php artisan ratings:backfill [options]
```

## Options

<deflist>
<def title="--batch-size=SIZE">
Number of events to process in each batch (default: 1000).
</def>
</deflist>

## Backfill Process

The command follows this comprehensive workflow:

1. **Scans historical events** from the beginning of recorded data
2. **Identifies rating events** that may contain missing ratings
3. **Extracts rating data** from event payloads
4. **Validates rating information** for completeness and accuracy
5. **Checks for existing ratings** to avoid duplicates
6. **Imports missing ratings** into the database
7. **Updates aggregate statistics** to reflect new data
8. **Reports backfill statistics** for monitoring

## Examples

<tabs>
<tab title="Standard Backfill">
<code-block lang="bash">
php artisan ratings:backfill
</code-block>
<p>Backfills missing ratings using default batch size.</p>
</tab>
<tab title="Large Batches">
<code-block lang="bash">
php artisan ratings:backfill --batch-size=5000
</code-block>
<p>Processes larger batches for faster completion (uses more memory).</p>
</tab>
<tab title="Small Batches">
<code-block lang="bash">
php artisan ratings:backfill --batch-size=500
</code-block>
<p>Uses smaller batches for systems with limited resources.</p>
</tab>
<tab title="Verbose Output">
<code-block lang="bash">
php artisan ratings:backfill -v
</code-block>
<p>Shows detailed progress and statistics during processing.</p>
</tab>
</tabs>

## When to Use

<procedure title="Recommended Usage Scenarios">
<step>After system downtime that may have missed rating imports</step>
<step>When discovering gaps in historical rating data</step>
<step>During initial database setup or migration</step>
<step>After improving rating detection algorithms</step>
<step>For comprehensive data quality assurance</step>
</procedure>

## Event Scanning

The backfill process examines various types of events:

### Rating Events

- **Direct Rating Events**: Explicit rating submissions
- **Review Events**: Reviews that include ratings
- **Update Events**: Rating changes or modifications

### Game Events

- **Publication Events**: May include initial ratings
- **Update Events**: Could contain rating information
- **Metadata Events**: Sometimes include rating data

### User Events

- **Profile Updates**: May reference rating activity
- **Collection Changes**: Could indicate rating preferences
- **Activity Events**: General user activity including ratings

## Data Validation

The backfill process includes comprehensive validation:

### Event Validation

- **Event Integrity**: Ensures events are complete and valid
- **Timestamp Validation**: Verifies event timing is reasonable
- **Source Verification**: Confirms events are from legitimate sources

### Rating Validation

- **Score Ranges**: Ensures ratings are within valid ranges (1-5 stars)
- **User Validation**: Verifies rating users exist and are valid
- **Game Validation**: Confirms rated games exist in the database
- **Duplicate Detection**: Prevents importing duplicate ratings

## Batch Processing

The command uses intelligent batch processing:

### Batch Size Selection

- **Small Batches (100-500)**: Lower memory usage, slower processing
- **Medium Batches (1000-2000)**: Balanced performance and resource usage
- **Large Batches (5000+)**: Faster processing, higher memory requirements

### Progress Tracking

- **Event Position**: Tracks current position in event stream
- **Completion Percentage**: Shows overall progress
- **Processing Rate**: Events processed per minute
- **ETA Calculation**: Estimated time to completion

## Gap Detection

The backfill process identifies various types of gaps:

### Temporal Gaps

- **Missing Time Periods**: Periods with no rating imports
- **Sparse Coverage**: Periods with unusually low rating activity
- **Event Sequence Gaps**: Missing events in chronological sequence

### Content Gaps

- **Game Coverage**: Games with missing or incomplete ratings
- **User Coverage**: Users whose ratings may be incomplete
- **Category Gaps**: Specific game categories with missing data

## Recovery and Resumption

The backfill process supports recovery from interruptions:

- **Position Tracking**: Remembers last processed event
- **Resume Capability**: Can continue from interruption point
- **State Preservation**: Maintains progress across restarts
- **Checkpoint System**: Regular progress checkpoints

> **Note**: Backfill operations can be very time-consuming for large event histories. Consider running during
> maintenance windows or off-peak hours.

## Related Commands

- [ratings:import](ratings-import.md) - Import current ratings from itch.io
- [games:refresh](games-refresh.md) - Refresh games that may have new ratings

# rater:mark-suspicious

Marks or unmarks a rater as suspicious for moderation purposes.

## Overview

This command allows administrators to flag users whose rating behavior appears suspicious or problematic. It supports
both marking users as suspicious with documented reasons and unmarking them if the suspicion is unfounded.

## Usage

```bash
php artisan rater:mark-suspicious [options] <rater_id>
```

## Arguments

rater_id
:   The ID of the rater to mark or unmark as suspicious.

## Options

--reason=TEXT
:   Reason for marking the rater as suspicious (required when marking).

--unmark
:   Remove the suspicious flag from the rater.

## Marking Process

When marking a rater as suspicious:

1. **Validates rater exists** in the database
2. **Records the reason** for the suspicious marking
3. **Updates rater status** to suspicious
4. **Logs the action** for audit purposes
5. **Applies rating filters** to exclude suspicious ratings

## Examples

=== "Mark Suspicious"

    ```bash
    php artisan rater:mark-suspicious 12345 --reason="Unusual rating patterns detected"
    ```
    <p>Marks rater ID 12345 as suspicious with the specified reason.</p>

=== "Spam Behavior"

    ```bash
    php artisan rater:mark-suspicious 67890 --reason="Posting spam reviews"
    ```
    <p>Marks a rater for spam behavior.</p>

=== "Unmark Suspicious"

    ```bash
    php artisan rater:mark-suspicious 12345 --unmark
    ```
    <p>Removes the suspicious flag from rater ID 12345.</p>

=== "Bot Activity"

    ```bash
    php artisan rater:mark-suspicious 11111 --reason="Automated rating behavior"
    ```
    <p>Marks a rater suspected of automated/bot activity.</p>

## When to Use

**Recommended Usage Scenarios**

1. When unusual rating patterns are detected
2. After receiving reports of spam or abuse
3. When investigating coordinated rating attacks
4. To temporarily exclude questionable ratings
5. When reversing previous moderation decisions

## Suspicious Behaviors

Common reasons for marking raters as suspicious:

### Rating Patterns

- **Mass Rating**: Rating many games in short time periods
- **Extreme Scores**: Only giving very high or very low ratings
- **Coordinated Activity**: Similar patterns with other suspicious users

### Content Issues

- **Spam Reviews**: Posting irrelevant or promotional content
- **Abusive Language**: Using inappropriate or offensive language
- **Copy-Paste Reviews**: Identical reviews across multiple games

### Technical Indicators

- **Bot Behavior**: Automated or scripted rating activity
- **IP Patterns**: Multiple accounts from same IP address
- **Timing Anomalies**: Ratings submitted at unusual intervals

## Impact of Marking

When a rater is marked as suspicious:

<table>
<tr>
    <td>Effect</td>
    <td>Description</td>
    <td>Reversible</td>
</tr>
<tr>
    <td>Rating Exclusion</td>
    <td>Ratings excluded from averages</td>
    <td>Yes</td>
</tr>
<tr>
    <td>Review Hiding</td>
    <td>Reviews hidden from public view</td>
    <td>Yes</td>
</tr>
<tr>
    <td>Audit Trail</td>
    <td>Action logged for review</td>
    <td>No (permanent record)</td>
</tr>
<tr>
    <td>Investigation Flag</td>
    <td>Marked for further review</td>
    <td>Yes</td>
</tr>
</table>

## Audit Trail

All marking actions create audit records including:

- **Action Timestamp**: When the action was taken
- **Administrator**: Who performed the action
- **Reason**: Documented reason for the action
- **Previous Status**: What the status was before the change
- **Evidence**: Any supporting information or references

## Unmarking Process

To unmark a suspicious rater:

1. **Verify the rater ID** is currently marked as suspicious
2. **Remove the suspicious flag** from the rater record
3. **Restore rating visibility** in calculations and displays
4. **Log the unmarking action** for audit purposes
5. **Update aggregate scores** to include restored ratings

## Quality Control

The suspicious marking system helps maintain:

- **Rating Accuracy**: Excludes problematic ratings from averages
- **User Trust**: Protects legitimate users from rating manipulation
- **Content Quality**: Reduces spam and abusive reviews
- **System Integrity**: Maintains reliable rating data

> **Warning**: Marking a rater as suspicious immediately affects their ratings' visibility. Ensure you have sufficient
> evidence before taking action.

## Related Commands

- [ratings:import](ratings-import.md) - Import ratings (excludes suspicious raters)
- [ratings:backfill](ratings-backfill.md) - Backfill ratings with filtering

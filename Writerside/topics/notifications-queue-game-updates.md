# notifications:queue-game-updates

Finds recently updated games and queues notifications for users who follow them.

## Overview

This command identifies games that have been updated recently and creates notification records for users who follow
those games. It's the first step in the notification pipeline, detecting update events and preparing notifications for
delivery.

## Usage

```bash
php artisan notifications:queue-game-updates [options]
```

## Options

<deflist>
<def title="--days=N">
Check for games updated in the last N days (default: 1).
</def>
<def title="--limit=COUNT">
Maximum number of games to process per run (default: 100).
</def>
</deflist>

## Detection Logic

The command follows this process:

1. **Identifies updated games** within the specified time window
2. **Finds users following** each updated game
3. **Checks notification preferences** for each user
4. **Creates notification records** for eligible users
5. **Prevents duplicate notifications** for the same update
6. **Reports queuing statistics** for monitoring

## Examples

<tabs>
<tab title="Default (Last Day)">
<code-block lang="bash">
php artisan notifications:queue-game-updates
</code-block>
<p>Queues notifications for games updated in the last 24 hours.</p>
</tab>
<tab title="Last Week">
<code-block lang="bash">
php artisan notifications:queue-game-updates --days=7
</code-block>
<p>Processes games updated in the last 7 days (useful after downtime).</p>
</tab>
<tab title="Limited Processing">
<code-block lang="bash">
php artisan notifications:queue-game-updates --limit=50
</code-block>
<p>Processes only the first 50 updated games.</p>
</tab>
<tab title="Recent Updates">
<code-block lang="bash">
php artisan notifications:queue-game-updates --days=0.5
</code-block>
<p>Processes games updated in the last 12 hours.</p>
</tab>
</tabs>

## When to Use

<procedure title="Recommended Usage Scenarios">
<step>Scheduled execution every few hours for regular notifications</step>
<step>Manual execution after system maintenance or downtime</step>
<step>Backlog processing with extended day ranges</step>
<step>Testing notification flow with specific games</step>
</procedure>

## Update Detection

The command detects various types of game updates:

### Version Updates

- New game versions released
- Updated download files
- Version metadata changes

### Content Updates

- Description or title changes
- New screenshots or media
- Tag or category updates

### Status Updates

- Publication status changes
- Pricing or availability updates
- Game jam participation

## Follower Matching

<table>
<tr>
    <td>Follow Type</td>
    <td>Notification Trigger</td>
    <td>User Preference</td>
</tr>
<tr>
    <td>Game Follow</td>
    <td>Any game update</td>
    <td>Game notifications enabled</td>
</tr>
<tr>
    <td>Creator Follow</td>
    <td>Creator's games updated</td>
    <td>Creator notifications enabled</td>
</tr>
<tr>
    <td>Collection Follow</td>
    <td>Collection games updated</td>
    <td>Collection notifications enabled</td>
</tr>
<tr>
    <td>Tag Follow</td>
    <td>Tagged games updated</td>
    <td>Tag notifications enabled</td>
</tr>
</table>

## Notification Preferences

The command respects user notification preferences:

### Global Settings

- **Notifications Enabled**: Master notification toggle
- **Update Frequency**: How often to receive notifications
- **Quiet Hours**: Time periods to avoid notifications

### Content Filters

- **Update Types**: Which types of updates to notify about
- **Game Categories**: Specific genres or tags to include/exclude
- **Creator Filters**: Specific creators to prioritize or mute

## Duplicate Prevention

The system prevents duplicate notifications through:

- **Update Tracking**: Records which updates have been processed
- **User Notification History**: Tracks what users have been notified about
- **Time Windows**: Prevents multiple notifications for the same update
- **Deduplication Logic**: Combines similar updates into single notifications

## Related Commands

- [notifications:process-push](notifications-process-push.md) - Deliver queued notifications
- [games:refresh](games-refresh.md) - Trigger game updates that create notifications

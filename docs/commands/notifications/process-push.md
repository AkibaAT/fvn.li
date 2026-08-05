# notifications:process-push

Processes pending browser push notifications and delivers them to users.

## Overview

This command handles the delivery of queued browser push notifications to users. It processes notifications in batches,
handles delivery failures, and manages retry logic for reliable notification delivery.

## Usage

```bash
php artisan notifications:process-push [options]
```

## Options

--limit=COUNT
:   Maximum number of notifications to process per run (default: 100).

--batch=SIZE
:   Number of notifications to process in a single batch (default: 20).

## Processing Logic

The command follows this workflow:

1. **Fetches pending notifications** from the queue
2. **Groups notifications** into manageable batches
3. **Delivers notifications** via browser push services
4. **Tracks delivery status** and handles failures
5. **Updates notification records** with delivery results
6. **Retries failed deliveries** according to retry policy

## Examples

=== "Default Processing"

    ```bash
    php artisan notifications:process-push
    ```
    <p>Processes up to 100 notifications in batches of 20.</p>

=== "Large Batch"

    ```bash
    php artisan notifications:process-push --limit=500 --batch=50
    ```
    <p>Processes up to 500 notifications in larger batches.</p>

=== "Small Batch"

    ```bash
    php artisan notifications:process-push --limit=50 --batch=10
    ```
    <p>Processes fewer notifications with smaller batches for testing.</p>

=== "Verbose Output"

    ```bash
    php artisan notifications:process-push -v
    ```
    <p>Shows detailed delivery information for each notification.</p>

## When to Use

**Recommended Usage Scenarios**

1. Scheduled execution every few minutes for timely delivery
2. Manual execution to clear notification backlogs
3. Testing notification delivery after configuration changes
4. Recovery processing after system downtime

## Batch Processing

<table>
<tr>
    <td>Batch Size</td>
    <td>Use Case</td>
    <td>Performance</td>
</tr>
<tr>
    <td>10-20</td>
    <td>Testing, low volume</td>
    <td>Lower throughput, safer</td>
</tr>
<tr>
    <td>20-50</td>
    <td>Normal operation</td>
    <td>Balanced performance</td>
</tr>
<tr>
    <td>50-100</td>
    <td>High volume periods</td>
    <td>Higher throughput</td>
</tr>
<tr>
    <td>100+</td>
    <td>Backlog clearing</td>
    <td>Maximum throughput</td>
</tr>
</table>

## Delivery Status

The command tracks various delivery outcomes:

### Successful Delivery

- Notification delivered to user's browser
- User device received and displayed notification
- Delivery confirmed by push service

### Failed Delivery

- **Invalid Subscription**: User unsubscribed or changed devices
- **Service Unavailable**: Push service temporarily down
- **Rate Limited**: Too many notifications sent too quickly
- **Invalid Payload**: Malformed notification data

### Retry Logic

- **Temporary Failures**: Automatic retry with exponential backoff
- **Permanent Failures**: Mark as failed, no further retries
- **Rate Limiting**: Respect service limits and retry later

## Push Service Integration

Supports multiple push notification services:

- **Web Push Protocol**: Standard browser push notifications
- **Firebase Cloud Messaging**: Google's push service
- **Apple Push Notification Service**: For Safari browsers
- **Microsoft WNS**: For Edge and Windows browsers

> **Note**: Delivery success depends on user browser settings, device connectivity, and push service availability.

## Related Commands

- [notifications:queue-game-updates](queue-game-updates.md): Create notifications for game updates

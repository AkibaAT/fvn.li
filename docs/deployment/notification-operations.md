# Notification deployment operations

The Laravel application and Discord bot must be deployed together when the notification API contract changes. Bot downtime is safe: claimed rows recover after 15 minutes and pending rows remain queued.

Before enabling delivery in production:

1. Set `QUEUE_CONNECTION=redis`, `LOG_STACK=daily`, and valid `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, and `VAPID_PRIVATE_KEY` values. Per-user Discord DMs and the admin alert feed are always on; only the multi-server guild announcements sit behind `DISCORD_SERVER_BOT_ENABLED`.
2. Enable User Install for the Discord application in the developer portal, and set the Webhook Events URL to `/api/discord/webhook-events` so authorization changes are recorded. Copy the application's public key into `DISCORD_PUBLIC_KEY`; without it every webhook event is rejected.
3. Re-issue the bot Sanctum token with both `discord-bot` and `discord-notifications` abilities.
4. Run `php artisan schedule-monitor:sync` (the deploy script runs this after migration) and confirm the `scheduler` compose service remains healthy.
5. Run `php artisan notifications:audit-discord-links`, review its output, then rerun with `--apply` to unlink invalid Discord IDs.
6. Run `php artisan model:prune --pretend` during an off-peak window, review the counts, then run `php artisan model:prune`.
7. Review `failed_jobs`, then remove entries older than seven days with `php artisan queue:prune-failed --hours=168`.
8. After confirming `LOG_STACK=daily` is active and a current daily log receives new entries, archive or truncate the legacy monolithic `storage/logs/laravel.log` using the host's normal log-retention procedure.

Monitoring should alert when `/health` reports a stale scheduler heartbeat, stale processing notifications, growing due work, or a growing failed-job count. A 503 response remains reserved for database or Redis connectivity failure.

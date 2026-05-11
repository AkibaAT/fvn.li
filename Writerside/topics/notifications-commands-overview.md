# Notification Commands Overview

Notification commands handle the processing and delivery of user notifications, particularly for game updates and
browser push notifications.

## Commands

| Command                            | Purpose                                    | Key Options                     |
|------------------------------------|--------------------------------------------|---------------------------------|
| `notifications:process-push`       | Process pending browser push notifications | `--limit=COUNT`, `--batch=SIZE` |
| `notifications:queue-game-updates` | Queue notifications for game updates       | `--days=N`, `--limit=COUNT`     |

Notification commands work in a pipeline: `queue-game-updates` finds updated games and creates notification records,
then `process-push` delivers them to users.

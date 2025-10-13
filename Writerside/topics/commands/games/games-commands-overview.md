# Games Commands Overview

Games commands handle various aspects of game data management, from refreshing information to processing media and
importing statistics.

## Commands

| Command                     | Purpose                                      | Key Options                                            |
|-----------------------------|----------------------------------------------|--------------------------------------------------------|
| `games:check-suspended`     | Check visible games for suspension status    | None                                                   |
| `games:cleanup-downloads`   | Clean up old game version downloads          | `--game-id=ID`, `--all`                                |
| `games:import-stats`        | Import stats JSON for a game version         | `--game-id=ID`, `--version-id=ID`, `--stats-file=PATH` |
| `games:process-screenshots` | Process and optimize game screenshots        | `--game-id=ID`, `--all`, `--quality=N`                 |
| `games:process-thumbnails`  | Process and optimize game thumbnails         | `--game-id=ID`, `--all`, `--quality=N`                 |
| `games:refresh`             | Refresh game information from itch.io        | `--game-id=ID`, `--all`, `--update-*`                  |
| `games:refresh-feedless`    | Refresh feedless games                       | Various filtering options                              |
| `games:reimport-version`    | Reimport version statistics                  | Version targeting options                              |
| `games:update-watchlist`    | Update games from itch.io collection         | Collection management options                          |

Games commands can be resource-intensive. Monitor API rate limits, use batch processing, and run media processing during
off-peak hours.

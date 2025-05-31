# Game Jam Commands Overview

Game jam commands handle fetching and updating detailed information about game jams from itch.io.

## Commands

| Command                   | Purpose                                | Key Options                                    |
|---------------------------|----------------------------------------|------------------------------------------------|
| `game-jams:fetch-details` | Fetch additional details for game jams | `--all`, `--id=ID`, `--name=NAME`, `--url=URL` |

Game jam commands fetch comprehensive information including jam metadata, participant counts, results, and associated
games. All commands include built-in retry logic for rate limiting.

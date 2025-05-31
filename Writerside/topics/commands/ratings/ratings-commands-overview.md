# Ratings Commands Overview

Ratings commands handle the import and management of game ratings data from itch.io.

## Commands

| Command            | Purpose                                     | Key Options             |
|--------------------|---------------------------------------------|-------------------------|
| `ratings:backfill` | Backfill missing ratings by scanning events | `--batch-size=SIZE`     |
| `ratings:import`   | Import latest ratings from itch.io          | None (automatic import) |

Ratings commands work together: `ratings:import` fetches the latest ratings from itch.io, while `ratings:backfill` fills
gaps by scanning historical events.

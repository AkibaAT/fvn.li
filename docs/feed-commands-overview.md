# Feed Commands Overview

Feed commands handle processing of the itch.io feed to discover and update game information automatically.

## Commands

| Command        | Purpose                               |
|----------------|---------------------------------------|
| `feed:process` | Process itch.io feed for game updates |

Feed commands are typically run automatically via scheduled tasks and include built-in retry logic for rate limiting and
API failures.

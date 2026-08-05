# Commands Overview

This section provides comprehensive documentation for all artisan commands available in the FVN.li project.

## Command Categories

### Fix Commands

Data repair and maintenance commands for ensuring database consistency.

| Command                             | Purpose                            | Key Options                                                   |
|-------------------------------------|------------------------------------|---------------------------------------------------------------|
| `fix:characters`                    | Comprehensive character data fixes | `--dry-run`, `--game-id=ID`, `--version-id=ID`, `--step=STEP` |
| `fix:platforms:incremental-support` | Report platform support issues     | `--game-id=ID` (report only)                                  |

### Feed Commands

Processing of the itch.io feed for automatic game discovery and updates.

| Command        | Purpose                               |
|----------------|---------------------------------------|
| `feed:process` | Process itch.io feed for game updates |

### Game Jam Commands

Management and enrichment of game jam information.

| Command                   | Purpose                                | Key Options                                    |
|---------------------------|----------------------------------------|------------------------------------------------|
| `game-jams:fetch-details` | Fetch additional details for game jams | `--all`, `--id=ID`, `--name=NAME`, `--url=URL` |

### Games Commands

Comprehensive game data management, media processing, and information updates.

| Command                     | Purpose                               | Key Options                                            |
|-----------------------------|---------------------------------------|--------------------------------------------------------|
| `games:cleanup-downloads`   | Clean up old game version downloads   | `--game-id=ID`, `--all`                                |
| `games:import-stats`        | Import stats JSON for a game version  | `--game-id=ID`, `--version-id=ID`, `--stats-file=PATH` |
| `games:process-screenshots` | Process and optimize game screenshots | `--game-id=ID`, `--all`, `--quality=N`                 |
| `games:process-thumbnails`  | Process and optimize game thumbnails  | `--game-id=ID`, `--all`, `--quality=N`                 |
| `games:refresh`             | Refresh game information from itch.io | `--game-id=ID`, `--all`, `--update-*`                  |
| `games:refresh-feedless`    | Refresh feedless games                | Various filtering options                              |
| `games:reimport-version`    | Reimport version statistics           | Version targeting options                              |
| `games:update-watchlist`    | Update games from watchlist           | Collection management options                          |

### Notification Commands

User notification processing and delivery system.

| Command                            | Purpose                                    | Key Options                     |
|------------------------------------|--------------------------------------------|---------------------------------|
| `notifications:process-push`       | Process pending browser push notifications | `--limit=COUNT`, `--batch=SIZE` |
| `notifications:queue-game-updates` | Queue notifications for game updates       | `--days=N`, `--limit=COUNT`     |

### Rater Commands

User rating behavior management and moderation tools.

| Command                 | Purpose                              | Key Options                 |
|-------------------------|--------------------------------------|-----------------------------|
| `rater:mark-suspicious` | Mark or unmark a rater as suspicious | `--reason=TEXT`, `--unmark` |

### Ratings Commands

Import and management of game ratings data from itch.io.

| Command            | Purpose                                     | Key Options             |
|--------------------|---------------------------------------------|-------------------------|
| `ratings:backfill` | Backfill missing ratings by scanning events | `--batch-size=SIZE`     |
| `ratings:import`   | Import latest ratings from itch.io          | None (automatic import) |

### Sitemap Commands

SEO optimization through XML sitemap generation.

| Command            | Purpose                       |
|--------------------|-------------------------------|
| `sitemap:generate` | Generate the sitemap.xml file |

## General Usage Guidelines

### Getting Help

Use `php artisan COMMAND --help` for detailed help on any specific command.

### Common Patterns

- **Dry Run**: Many commands support `--dry-run` to preview changes
- **Targeting**: Most commands support `--game-id=ID` for specific games
- **Batch Processing**: Commands often include `--limit` options for batch control
- **Verbose Output**: Use `-v` for detailed execution information

### Performance Considerations

- **Resource Usage**: Media processing and bulk operations can be resource-intensive
- **API Limits**: Commands that interact with itch.io respect rate limiting
- **Batch Sizes**: Adjust batch sizes based on system resources and requirements
- **Scheduling**: Many commands are designed for automated/scheduled execution

### Error Handling

All commands include comprehensive error handling with retry logic for temporary failures and detailed logging for
troubleshooting.

## Data Flow Diagrams

### Game Update Processing Flow

This diagram shows how game updates flow from itch.io through the system to user notifications:

```mermaid
graph TD
    A[itch.io Public Feed] --> B[feed:process]
    B --> C{Game Exists in DB?}
    C -->|No| D[Skip Event]
    C -->|Yes| E[Refresh Version Info]

    E --> F[Record Processed Event]
    D --> G[Continue Processing]

    F --> H[notifications:queue-game-updates]
    H --> I{User Follows Game?}
    I -->|Yes| J[Create Notification Record]
    I -->|No| K[Skip User]

    J --> L[notifications:process-push]
    L --> M[Send Push Notification]
    M --> N[User Receives Update]

```

### Watchlist and Creator Discovery Flow

This diagram shows how new games are discovered through watchlists and creator following:

```mermaid
graph TD
    A[itch.io Collections] --> B[games:update-watchlist]
    B --> C{Game in Database?}
    C -->|No| D[Add New Game]
    C -->|Yes| E[Update Game Info]

    D --> F[Follow Creator]
    E --> G{Game Visibility Changed?}
    G -->|Yes| F
    G -->|No| H[Skip Creator Follow]

    F --> I[Discover Creator Catalog]
    I --> J{New Games Found?}
    J -->|Yes| K[Add Creator Games]
    J -->|No| L[Complete]

    K --> M[games:refresh]
    M --> N[Load Full Game Details]
    N --> O[Process Screenshots/Thumbnails]
    O --> P[Import Game Statistics]
    P --> L

```

### Game Data Processing Pipeline

This diagram shows the complete game data processing pipeline from import to analysis:

```mermaid
graph TD
    A[New Game Added] --> B[games:refresh]
    B --> C[Fetch Base Info]
    C --> D[Fetch Version Info]
    D --> E[Fetch Metadata]
    E --> F[Download Game Archive]

    F --> G[games:reimport-version]
    G --> H[Extract Game Files]
    H --> I[Analyze Game Content]
    I --> J[Extract Character Data]
    J --> K[Calculate Statistics]

    K --> L[fix:characters]

    L --> O[games:process-screenshots]
    O --> P[games:process-thumbnails]
    P --> Q[sitemap:generate]
    Q --> R[Complete]

```

### Ratings and Game Jam Data Flow

This diagram shows how ratings and game jam information are processed:

```mermaid
graph TD
    A[itch.io Ratings API] --> B[ratings:import]
    B --> C[Import User Ratings]
    C --> D[Calculate Aggregates]
    D --> E[rater:mark-suspicious]
    E --> F{Suspicious Rater?}
    F -->|Yes| G[Exclude from Aggregates]
    F -->|No| H[Include in Aggregates]

    I[Game Jam Discovery] --> J[game-jams:fetch-details]
    J --> K[Fetch Jam Metadata]
    K --> L[Fetch Participants]
    L --> M[Fetch Results]
    M --> N[Associate Games with Jams]

    O[Historical Events] --> P[ratings:backfill]
    P --> Q[Scan Event History]
    Q --> R[Extract Missing Ratings]
    R --> C

    G --> S[Update Game Ratings]
    H --> S
    N --> T[Update Game Metadata]
    S --> U[Complete]
    T --> U

```

### System Architecture Overview

This diagram shows the overall command architecture and how different command categories interact:

```mermaid
graph TB
    subgraph "Data Sources"
        A[itch.io Public Feed]
        B[itch.io Collections]
        C[itch.io API]
        D[Game Archives]
    end

    subgraph "Import Commands"
        E[feed:process]
        F[games:update-watchlist]
        G[games:refresh]
        H[ratings:import]
    end

    subgraph "Processing Commands"
        I[games:reimport-version]
        J[games:process-screenshots]
        K[games:process-thumbnails]
        L[game-jams:fetch-details]
    end

    subgraph "Fix Commands"
        M[fix:characters]
        N[fix:platforms:incremental-support]
    end

    subgraph "Notification Commands"
        Q[notifications:queue-game-updates]
        R[notifications:process-push]
    end

    subgraph "Maintenance Commands"
        S[games:cleanup-downloads]
        T[ratings:backfill]
        U[rater:mark-suspicious]
        V[sitemap:generate]
    end

    A --> E
    B --> F
    C --> G
    C --> H
    D --> I

    E --> G
    F --> G
    G --> I
    G --> J
    G --> K
    G --> L

    I --> M

    G --> Q
    Q --> R

```

# Fix Commands Overview

Fix commands are designed to repair and maintain data consistency across the application. These commands follow a
hierarchical naming structure and provide comprehensive data maintenance capabilities.

## Commands

| Command                             | Purpose                            | Key Options                                                   |
|-------------------------------------|------------------------------------|---------------------------------------------------------------|
| `fix:characters`                    | Comprehensive character data fixes | `--dry-run`, `--game-id=ID`, `--version-id=ID`, `--step=STEP` |
| `fix:platforms:incremental-support` | Report platform support issues     | `--game-id=ID` (report only)                                  |

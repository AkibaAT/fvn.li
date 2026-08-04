# games:refresh-feedless

Refreshes version information for visible itch.io games whose `is_feedless` flag is set.

## Usage

```bash
php artisan games:refresh-feedless [options]
```

One selection option is required:

- `--game-id=<id>` selects a game by ID
- `--game-name=<name>` selects games whose names match the supplied value
- `--all` selects every visible feedless itch.io game

## Examples

```bash
php artisan games:refresh-feedless --all
php artisan games:refresh-feedless --game-id=123
php artisan games:refresh-feedless --game-name="Example VN"
```

## Behavior

The command orders selected games by ID and refreshes each game's version. Successful refreshes clear the stored error; failures store the exception message on the game and continue with the remaining selection. It waits ten seconds between games and manages a FlareSolverr session around the run.

The command exits with status `1` when the selection is invalid, no games match, or the outer refresh process fails. Otherwise it exits with status `0` after processing the selection.

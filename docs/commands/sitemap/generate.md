# sitemap:generate

Generates `public/sitemap.xml` from the application's public game routes.

## Usage

```bash
php artisan sitemap:generate
```

The command has no application-specific options.

## Included URLs

The generated sitemap contains:

- The homepage, with daily change frequency and priority `1.0`
- The first games listing page, with daily change frequency and priority `0.9`
- Additional games listing pages, based on nine visible games per page, with daily change frequency and priority `0.8`
- Detail pages for visible games that have a slug, with weekly change frequency, priority `0.9`, and the game's `updated_at` timestamp

The command writes the sitemap directly to `public/sitemap.xml` and reports when generation completes.

## Related Commands

- [games:refresh](../games/refresh.md): Refresh game data included in the sitemap
- [feed:process](../feed/process.md): Process newly discovered itch.io games

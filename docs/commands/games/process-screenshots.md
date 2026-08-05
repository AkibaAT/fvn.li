# games:process-screenshots

Processes and optimizes game screenshots for web display.

## Overview

This command processes game screenshots by converting them to optimized WebP format, resizing them for consistent
display, and generating thumbnails. It helps improve website performance by reducing image file sizes while maintaining
visual quality.

## Usage

```bash
php artisan games:process-screenshots [options]
```

## Options

--force
:   Process screenshots even if they already exist.

--game-id=ID
:   ID of the specific game to process.

--game-name=NAME
:   Name (or part of name) of the game(s) to process.

--all
:   Process all visible games with screenshots.

--quality=QUALITY
:   WebP quality (0-100) (default: 80).

## Processing Logic

The command follows this workflow:

1. **Identifies games** with screenshots to process
2. **Downloads original images** if not already cached
3. **Converts to WebP format** with specified quality
4. **Resizes images** to standard dimensions
5. **Generates thumbnails** for gallery views
6. **Optimizes file sizes** while preserving quality
7. **Updates database records** with processed image paths

## Examples

=== "Specific Game"

    ```bash
    php artisan games:process-screenshots --game-id=123
    ```
    <p>Processes screenshots for game ID 123.</p>

=== "High Quality"

    ```bash
    php artisan games:process-screenshots --game-id=123 --quality=90
    ```
    <p>Processes with higher quality settings (larger files).</p>

=== "Force Reprocess"

    ```bash
    php artisan games:process-screenshots --game-id=123 --force
    ```
    <p>Reprocesses screenshots even if they already exist.</p>

=== "Batch Processing"

    ```bash
    php artisan games:process-screenshots --all
    ```
    <p>Processes screenshots for all games (may take considerable time).</p>

## When to Use

**Recommended Usage Scenarios**

1. After importing new games with screenshots
2. When optimizing website performance
3. After changing image quality settings
4. During initial setup or migration
5. When storage space needs optimization

## Image Processing

The command performs several image optimizations:

### Format Conversion

- **WebP Output**: Modern format with superior compression
- **Quality Control**: Configurable quality vs. file size balance
- **Fallback Support**: Maintains compatibility with older browsers

### Size Optimization

- **Standard Dimensions**: Consistent sizing for layout
- **Responsive Variants**: Multiple sizes for different displays
- **Thumbnail Generation**: Small previews for galleries

### Quality Enhancement

- **Sharpening**: Improves clarity after resizing
- **Color Optimization**: Maintains color accuracy
- **Compression**: Reduces file size without visible quality loss

## Quality Settings

Different quality settings provide different trade-offs:

### Quality 60-70

- **Use Case**: Maximum compression for bandwidth-limited environments
- **File Size**: Smallest files
- **Visual Quality**: Acceptable for thumbnails

### Quality 80 (Default)

- **Use Case**: Balanced compression for general use
- **File Size**: Good compression ratio
- **Visual Quality**: High quality for most purposes

### Quality 90-100

- **Use Case**: Maximum quality for detailed screenshots
- **File Size**: Larger files
- **Visual Quality**: Excellent for detailed viewing

## Output Formats

The processing generates multiple image variants:

### Full Size Images

- **Format**: WebP with specified quality
- **Dimensions**: Optimized for detail viewing
- **Use**: Game detail pages and lightboxes

### Medium Images

- **Format**: WebP with balanced compression
- **Dimensions**: Suitable for gallery displays
- **Use**: Screenshot galleries and previews

### Thumbnails

- **Format**: WebP with higher compression
- **Dimensions**: Small previews
- **Use**: Game cards and quick previews

## Storage Organization

Processed images are organized efficiently:

```
storage/
├── screenshots/
│   ├── full/
│   │   └── game-123-screenshot-1.webp
│   ├── medium/
│   │   └── game-123-screenshot-1.webp
│   └── thumbnails/
│       └── game-123-screenshot-1.webp
```

> **Warning**: Processing all screenshots can consume significant CPU, memory, and storage resources. Monitor system
> performance during large batch operations.

## Related Commands

- [games:process-thumbnails](process-thumbnails.md): Process game thumbnails
- [games:refresh](refresh.md): Download new screenshots for processing

# games:process-screenshots

Processes and optimizes game screenshots for web display.

## Overview

This command processes game screenshots by converting them to optimized WebP format, resizing them for consistent
display, and generating thumbnails. It helps improve website performance by reducing image file sizes while maintaining
visual quality.

**Key Features**: WebP conversion, quality optimization, batch processing, memory-efficient handling.

## Usage

```bash
php artisan games:process-screenshots [options]
```

## Options

<deflist>
<def title="--force">
Process screenshots even if they already exist.
</def>
<def title="--game-id=ID">
ID of the specific game to process.
</def>
<def title="--game-name=NAME">
Name (or part of name) of the game(s) to process.
</def>
<def title="--all">
Process all visible games with screenshots.
</def>
<def title="--quality=QUALITY">
WebP quality (0-100) (default: 80).
</def>
</deflist>

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

<tabs>
<tab title="Specific Game">
<code-block lang="bash">
php artisan games:process-screenshots --game-id=123
</code-block>
<p>Processes screenshots for game ID 123.</p>
</tab>
<tab title="High Quality">
<code-block lang="bash">
php artisan games:process-screenshots --game-id=123 --quality=90
</code-block>
<p>Processes with higher quality settings (larger files).</p>
</tab>
<tab title="Force Reprocess">
<code-block lang="bash">
php artisan games:process-screenshots --game-id=123 --force
</code-block>
<p>Reprocesses screenshots even if they already exist.</p>
</tab>
<tab title="Batch Processing">
<code-block lang="bash">
php artisan games:process-screenshots --all
</code-block>
<p>Processes screenshots for all games (may take considerable time).</p>
</tab>
</tabs>

## When to Use

<procedure title="Recommended Usage Scenarios">
<step>After importing new games with screenshots</step>
<step>When optimizing website performance</step>
<step>After changing image quality settings</step>
<step>During initial setup or migration</step>
<step>When storage space needs optimization</step>
</procedure>

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

## Performance Considerations

<table>
<tr>
    <td>Factor</td>
    <td>Impact</td>
    <td>Mitigation</td>
</tr>
<tr>
    <td>Memory Usage</td>
    <td>High for large images</td>
    <td>Process in batches</td>
</tr>
<tr>
    <td>Processing Time</td>
    <td>Varies by image count</td>
    <td>Run during off-peak hours</td>
</tr>
<tr>
    <td>Storage Space</td>
    <td>Temporary increase during processing</td>
    <td>Monitor disk space</td>
</tr>
<tr>
    <td>Network Bandwidth</td>
    <td>Downloads original images</td>
    <td>Consider bandwidth limits</td>
</tr>
</table>

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

## Error Handling

The command handles various error conditions:

- **Download Failures**: Retry logic for network issues
- **Invalid Images**: Skip corrupted or unsupported files
- **Processing Errors**: Continue with remaining images
- **Storage Issues**: Handle disk space and permission problems

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

- [games:process-thumbnails](games-process-thumbnails.md) - Process game thumbnails
- [games:refresh](games-refresh.md) - Download new screenshots for processing

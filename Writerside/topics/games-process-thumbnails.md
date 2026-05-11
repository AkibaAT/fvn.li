# games:process-thumbnails

Processes and optimizes game thumbnails for efficient display across the website.

## Overview

This command processes game thumbnail images by converting them to optimized formats, generating multiple sizes for
responsive display, and ensuring consistent visual presentation. It's essential for maintaining fast page load times and
consistent user experience.

**Key Features**: Multi-size generation, format optimization, batch processing, responsive image support.

## Usage

```bash
php artisan games:process-thumbnails [options]
```

## Options

Similar to screenshot processing, this command supports:

<deflist>
<def title="--force">
Process thumbnails even if they already exist.
</def>
<def title="--game-id=ID">
ID of the specific game to process.
</def>
<def title="--game-name=NAME">
Name (or part of name) of the game(s) to process.
</def>
<def title="--all">
Process all visible games with thumbnails.
</def>
<def title="--quality=QUALITY">
WebP quality (0-100) (default: 80).
</def>
</deflist>

## Processing Workflow

The command processes thumbnails through these steps:

1. **Identifies games** requiring thumbnail processing
2. **Downloads source images** from itch.io or local storage
3. **Generates multiple sizes** for responsive display
4. **Converts to WebP format** for optimal compression
5. **Creates fallback formats** for browser compatibility
6. **Optimizes file sizes** while maintaining visual quality
7. **Updates database records** with processed thumbnail paths

## Examples

<tabs>
<tab title="Single Game">
<code-block lang="bash">
php artisan games:process-thumbnails --game-id=123
</code-block>
<p>Processes thumbnails for a specific game.</p>
</tab>
<tab title="By Name Pattern">
<code-block lang="bash">
php artisan games:process-thumbnails --game-name="Visual Novel"
</code-block>
<p>Processes thumbnails for games matching the name pattern.</p>
</tab>
<tab title="Force Regeneration">
<code-block lang="bash">
php artisan games:process-thumbnails --all --force
</code-block>
<p>Regenerates all thumbnails regardless of existing files.</p>
</tab>
<tab title="High Quality">
<code-block lang="bash">
php artisan games:process-thumbnails --game-id=123 --quality=95
</code-block>
<p>Processes with higher quality settings for better visual fidelity.</p>
</tab>
</tabs>

## When to Use

<procedure title="Recommended Usage Scenarios">
<step>After importing new games with thumbnail images</step>
<step>When implementing responsive design changes</step>
<step>During website performance optimization</step>
<step>After updating image processing algorithms</step>
<step>When migrating to new image formats</step>
</procedure>

## Thumbnail Sizes

The command generates multiple thumbnail sizes:

### Large Thumbnails (400x600px)

- **Use Case**: Featured game displays, hero sections
- **Quality**: High detail for prominent placement
- **Compression**: Moderate to preserve detail

### Medium Thumbnails (200x300px)

- **Use Case**: Game grid displays, search results
- **Quality**: Balanced detail and file size
- **Compression**: Standard optimization

### Small Thumbnails (100x150px)

- **Use Case**: Compact lists, mobile displays
- **Quality**: Optimized for small display
- **Compression**: Higher compression acceptable

### Micro Thumbnails (50x75px)

- **Use Case**: Tiny previews, loading placeholders
- **Quality**: Basic recognition quality
- **Compression**: Maximum compression

## Format Support

<table>
<tr>
    <td>Format</td>
    <td>Use Case</td>
    <td>Browser Support</td>
</tr>
<tr>
    <td>WebP</td>
    <td>Primary format</td>
    <td>Modern browsers</td>
</tr>
<tr>
    <td>JPEG</td>
    <td>Fallback format</td>
    <td>Universal support</td>
</tr>
<tr>
    <td>PNG</td>
    <td>Transparency support</td>
    <td>Universal support</td>
</tr>
<tr>
    <td>AVIF</td>
    <td>Future format</td>
    <td>Cutting-edge browsers</td>
</tr>
</table>

## Performance Impact

Thumbnail processing affects system resources:

### CPU Usage

- **Image Decoding**: Processing original images
- **Format Conversion**: Converting between formats
- **Resizing Operations**: Scaling to different sizes
- **Compression**: Optimizing file sizes

### Memory Requirements

- **Image Buffers**: Temporary storage during processing
- **Multiple Variants**: Simultaneous size generation
- **Batch Processing**: Memory usage scales with batch size

### Storage Impact

- **Multiple Files**: Each thumbnail generates several variants
- **Temporary Files**: Processing creates temporary files
- **Archive Storage**: Original images may be preserved

## Quality Optimization

The command balances quality and file size:

### Visual Quality Factors

- **Sharpness**: Maintains clarity at smaller sizes
- **Color Accuracy**: Preserves original color representation
- **Detail Preservation**: Retains important visual elements
- **Artifact Reduction**: Minimizes compression artifacts

### File Size Optimization

- **Compression Algorithms**: Uses efficient compression methods
- **Format Selection**: Chooses optimal format for each use case
- **Quality Settings**: Adjusts quality based on thumbnail size
- **Progressive Loading**: Supports progressive image loading

## Error Handling

Robust error handling ensures reliable processing:

- **Source Image Issues**: Handles corrupted or missing source images
- **Processing Failures**: Continues with remaining thumbnails on errors
- **Storage Problems**: Manages disk space and permission issues
- **Network Failures**: Retries downloads with exponential backoff

## Integration

Thumbnail processing integrates with other systems:

- **CDN Distribution**: Processed thumbnails can be distributed via CDN
- **Responsive Images**: Generates srcset attributes for responsive display
- **Lazy Loading**: Supports progressive image loading strategies
- **Cache Management**: Integrates with browser and server caching

> **Note**: Thumbnail processing is typically faster than screenshot processing due to smaller image sizes, but can
> still be resource-intensive for large batches.

## Related Commands

- [games:process-screenshots](games-process-screenshots.md) - Process full-size screenshots
- [games:refresh](games-refresh.md) - Download new thumbnail images for processing

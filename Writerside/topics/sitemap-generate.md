# sitemap:generate

Generates the sitemap.xml file for search engine optimization.

## Overview

This command creates a comprehensive XML sitemap containing all publicly accessible URLs on the website. The sitemap
helps search engines discover and index content more efficiently, improving SEO performance.

**Key Features**: Automatic URL discovery, priority weighting, change frequency hints, XML validation.

## Usage

```bash
php artisan sitemap:generate
```

## Options

This command has no specific options - it automatically generates a complete sitemap using configured settings.

## Generation Process

The command follows this workflow:

1. **Discovers all public URLs** from the application routes
2. **Queries database** for dynamic content (games, creators, etc.)
3. **Calculates priorities** based on content importance
4. **Determines change frequencies** based on content type
5. **Generates XML sitemap** following sitemap protocol
6. **Validates XML output** for compliance
7. **Saves sitemap file** to public directory

## Examples

<tabs>
<tab title="Standard Generation">
<code-block lang="bash">
php artisan sitemap:generate
</code-block>
<p>Generates a complete sitemap.xml file with all public URLs.</p>
</tab>
<tab title="Verbose Output">
<code-block lang="bash">
php artisan sitemap:generate -v
</code-block>
<p>Shows detailed information about URLs being included.</p>
</tab>
<tab title="Quiet Mode">
<code-block lang="bash">
php artisan sitemap:generate --quiet
</code-block>
<p>Only shows errors, useful for automated execution.</p>
</tab>
</tabs>

## When to Use

<procedure title="Recommended Usage Scenarios">
<step>Scheduled execution (daily or weekly) for regular updates</step>
<step>After adding new games or content to the database</step>
<step>When launching new features or pages</step>
<step>After significant content updates or reorganization</step>
<step>During SEO optimization campaigns</step>
</procedure>

## URL Categories

The sitemap includes various types of URLs:

### Static Pages

- **Homepage**: Main site entry point
- **About/Help Pages**: Informational content
- **Category Pages**: Game browsing and filtering
- **Search Pages**: Game discovery interfaces

### Dynamic Content

- **Game Detail Pages**: Individual game information
- **Creator Profiles**: Developer and publisher pages
- **Game Jam Pages**: Event and competition information
- **Collection Pages**: Curated game lists

### API Endpoints

- **Public APIs**: Documented API endpoints
- **RSS Feeds**: Syndication feeds
- **Data Exports**: Public data access points

## Priority Weighting

<table>
<tr>
    <td>Content Type</td>
    <td>Priority</td>
    <td>Reasoning</td>
</tr>
<tr>
    <td>Homepage</td>
    <td>1.0</td>
    <td>Most important entry point</td>
</tr>
<tr>
    <td>Popular Games</td>
    <td>0.9</td>
    <td>High-traffic content</td>
</tr>
<tr>
    <td>Game Detail Pages</td>
    <td>0.8</td>
    <td>Primary content</td>
</tr>
<tr>
    <td>Creator Pages</td>
    <td>0.7</td>
    <td>Important discovery pages</td>
</tr>
<tr>
    <td>Category Pages</td>
    <td>0.6</td>
    <td>Navigation and browsing</td>
</tr>
<tr>
    <td>Static Pages</td>
    <td>0.5</td>
    <td>Supporting content</td>
</tr>
</table>

## Change Frequency

The sitemap includes change frequency hints:

### Daily Updates

- **Homepage**: Frequently updated with new content
- **Popular Games**: Rankings and statistics change daily
- **Recent Releases**: New games added regularly

### Weekly Updates

- **Game Detail Pages**: Occasional updates and new versions
- **Creator Profiles**: Periodic information updates
- **Category Pages**: Content additions and changes

### Monthly Updates

- **Static Pages**: Infrequent content changes
- **Archive Pages**: Historical content rarely changes
- **Documentation**: Occasional updates and improvements

## File Output

The generated sitemap:

- **Location**: Saved to `public/sitemap.xml`
- **Format**: Valid XML following sitemap protocol
- **Size Limits**: Respects 50,000 URL and 50MB limits
- **Compression**: Optionally gzipped for bandwidth efficiency

## SEO Benefits

A well-maintained sitemap provides:

- **Faster Indexing**: Search engines discover new content quickly
- **Complete Coverage**: Ensures all important pages are found
- **Priority Signals**: Helps search engines understand content importance
- **Update Notifications**: Indicates when content has changed

## Validation

The command includes validation to ensure:

- **XML Compliance**: Proper XML syntax and structure
- **URL Validity**: All URLs are properly formatted and accessible
- **Protocol Adherence**: Follows sitemap.org specifications
- **Size Limits**: Stays within search engine limits

## Performance Optimization

The generation process is optimized for:

- **Database Efficiency**: Minimal queries with proper indexing
- **Memory Management**: Handles large numbers of URLs efficiently
- **Processing Speed**: Quick generation even for large sites
- **File I/O**: Efficient XML writing and file operations

> **Note**: The sitemap is automatically submitted to search engines if webmaster tools are configured.

## Related Commands

- [games:refresh](games-refresh.md) - Update content that affects sitemap
- [feed:process](feed-process.md) - Add new content for sitemap inclusion

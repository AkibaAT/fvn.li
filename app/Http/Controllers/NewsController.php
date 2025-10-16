<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\News;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    /**
     * Display a listing of published news.
     */
    public function index(): Response
    {
        $news = News::published()
            ->orderByDesc('published_at')
            ->with('author')
            ->paginate(10);

        // Add excerpt to each news item
        $news->getCollection()->transform(function ($item) {
            $item->excerpt = $this->generateExcerpt($item->content, 200);
            return $item;
        });

        $metaTags = [
            'title' => 'News & Announcements - FVN.li',
            'description' => 'Stay updated with the latest news, announcements, and updates from FVN.li. Get information about new features, maintenance schedules, and important site updates.',
            'image' => asset(config('social.images.news', config('social.images.default'))),
            'url' => url('/news'),
            'type' => 'website',
            'twitterCard' => 'summary_large_image',
        ];

        return Inertia::render('news/index', [
            'news' => $news,
            'metaTags' => $metaTags,
        ]);
    }

    /**
     * Generate a plain text excerpt from HTML content.
     */
    private function generateExcerpt(string $html, int $length = 200): string
    {
        // Add spacing after block-level elements before stripping tags
        $html = preg_replace('/<\/(p|div|h[1-6]|li|blockquote|pre)>/i', '$0 ', $html);

        // Add spacing after br tags
        $html = preg_replace('/<br\s*\/?>/i', ' ', $html);

        // Strip HTML tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace (collapse multiple spaces into one)
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Truncate to length
        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length);
            // Try to break at a word boundary
            $lastSpace = mb_strrpos($text, ' ');
            if ($lastSpace !== false && $lastSpace > $length * 0.8) {
                $text = mb_substr($text, 0, $lastSpace);
            }
            $text .= '...';
        }

        return $text;
    }

    /**
     * Display a specific news item.
     */
    public function show(News $news): Response
    {
        // Only show published news to non-admin users
        if (!$news->is_published && (!auth()->check() || !auth()->user()?->is_admin)) {
            abort(404);
        }

        $news->load('author');

        // Generate clean excerpt for description
        $excerpt = $this->generateExcerpt($news->content, 200);

        // Determine article type based on news type
        $articleType = match ($news->type) {
            'announcement' => 'Announcement',
            'update' => 'Update',
            'maintenance' => 'Maintenance',
            'incident' => 'Incident',
            default => 'News',
        };

        $metaTags = [
            'title' => $news->title,
            'description' => $excerpt,
            'image' => asset(config('social.images.news', config('social.images.default'))),
            'url' => url("/news/{$news->slug}"),
            'type' => 'article',
            'twitterCard' => 'summary_large_image',
            'author' => $news->author->name,
            'publishedTime' => $news->published_at?->toIso8601String(),
            'modifiedTime' => $news->updated_at->toIso8601String(),
            'section' => $articleType,
        ];

        return Inertia::render('news/show', [
            'newsItem' => $news,
            'metaTags' => $metaTags,
        ]);
    }
}


{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $title }}</title>
        <description>{{ $description }}</description>
        <link>{{ $link }}</link>
        <language>en-us</language>
        <lastBuildDate>{{ now()->toRfc2822String() }}</lastBuildDate>
        <atom:link href="{{ request()->url() }}" rel="self" type="application/rss+xml"/>
        @foreach ($games as $game)
        <item>
            <title>{{ htmlspecialchars($game->effective_name, ENT_XML1, 'UTF-8') }}</title>
            <link>{{ route('games.show', $game) }}</link>
            <guid isPermaLink="true">{{ route('games.show', $game) }}</guid>
            <description>{{ htmlspecialchars($game->description ?? '', ENT_XML1, 'UTF-8') }}</description>
            @if (isset($isUpdates) && $isUpdates && $game->latestVersion?->published_at)
            <pubDate>{{ $game->latestVersion->published_at->toRfc2822String() }}</pubDate>
            @elseif ($game->first_visible_at)
            <pubDate>{{ $game->first_visible_at->toRfc2822String() }}</pubDate>
            @elseif ($game->created_at)
            <pubDate>{{ $game->created_at->toRfc2822String() }}</pubDate>
            @endif
            @if ($game->authors)
            <author>{{ htmlspecialchars(strip_tags($game->authors), ENT_XML1, 'UTF-8') }}</author>
            @endif
        </item>
        @endforeach
    </channel>
</rss>

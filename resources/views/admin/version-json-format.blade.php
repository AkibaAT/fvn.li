<x-filament::page>
    <x-filament::section>
        <h1 class="text-2xl font-bold mb-4 text-fg">Game Version JSON Format</h1>

        <p class="mb-4 text-fg-muted">
            This page explains the JSON format used for importing and exporting game version data.
            You can use the "Export JSON" action on any existing version to get a template for creating new versions.
        </p>

        <h2 class="text-xl font-semibold mt-6 mb-2 text-fg">Basic Structure</h2>
        <pre class="bg-surface-alt p-4 rounded-lg overflow-auto mb-4 text-fg">
{
    "version": "1.0.0",
    "published_at": "2023-01-01T00:00:00+00:00",
    "is_windows": true,
    "is_linux": false,
    "is_mac": false,
    "is_android": false,
    "is_web": false,
    "rating": 4.5,
    "rating_count": 10,
    "devlog": "https://example.com/devlog",
    "character_stats": [...],
    "language_stats": [...],
    "supported_languages": [...]
}
        </pre>

        <h2 class="text-xl font-semibold mt-6 mb-2 text-fg">Character Stats</h2>
        <p class="mb-4 text-fg-muted">
            The <code
                class="bg-surface-alt px-1 rounded text-fg">character_stats</code>
            array contains statistics for each character in each language:
        </p>
        <pre class="bg-surface-alt p-4 rounded-lg overflow-auto mb-4 text-fg">
"character_stats": [
    {
        "character_id": "protagonist",
        "iso_code": "eng",
        "blocks": 100,
        "words": 500
    },
    {
        "character_id": "supporting",
        "iso_code": "eng",
        "blocks": 50,
        "words": 250
    }
]
        </pre>

        <h2 class="text-xl font-semibold mt-6 mb-2 text-fg">Language Stats</h2>
        <p class="mb-4 text-fg-muted">
            The <code
                class="bg-surface-alt px-1 rounded text-fg">language_stats</code>
            array contains overall statistics for each language:
        </p>
        <pre class="bg-surface-alt p-4 rounded-lg overflow-auto mb-4 text-fg">
"language_stats": [
    {
        "iso_code": "eng",
        "blocks": 150,
        "words": 750
    },
    {
        "iso_code": "jpn",
        "blocks": 150,
        "words": 300
    }
]
        </pre>

        <h2 class="text-xl font-semibold mt-6 mb-2 text-fg">Supported Languages</h2>
        <p class="mb-4 text-fg-muted">
            The <code class="bg-surface-alt px-1 rounded text-fg">supported_languages</code>
            array lists all languages supported by this version:
        </p>
        <pre class="bg-surface-alt p-4 rounded-lg overflow-auto mb-4 text-fg">
"supported_languages": [
    {
        "iso_code": "eng",
        "is_available": true
    },
    {
        "iso_code": "jpn",
        "is_available": true
    }
]
        </pre>

        <h2 class="text-xl font-semibold mt-6 mb-2 text-fg">Notes</h2>
        <ul class="list-disc pl-6 mb-4 text-fg-muted">
            <li>All fields except <code class="bg-surface-alt px-1 rounded text-fg">version</code>
                and <code
                    class="bg-surface-alt px-1 rounded text-fg">published_at</code>
                are optional
            </li>
            <li>If <code
                    class="bg-surface-alt px-1 rounded text-fg">published_at</code>
                is not provided, the current date and time will be used
            </li>
            <li>Platform fields (<code class="bg-surface-alt px-1 rounded text-fg">is_windows</code>,
                etc.) default to <code class="bg-surface-alt px-1 rounded text-fg">false</code>
                if not provided
            </li>
            <li>The <code class="bg-surface-alt px-1 rounded text-fg">character_stats</code>,
                <code
                    class="bg-surface-alt px-1 rounded text-fg">language_stats</code>,
                and <code class="bg-surface-alt px-1 rounded text-fg">supported_languages</code>
                arrays can be empty or omitted
            </li>
        </ul>
    </x-filament::section>
</x-filament::page>

<x-filament::page>
    <x-filament::section>
        <h1 class="text-2xl font-bold mb-4 text-gray-900 dark:text-white">Game Version JSON Format</h1>

        <p class="mb-4 text-gray-700 dark:text-white">
            This page explains the JSON format used for importing and exporting game version data.
            You can use the "Export JSON" action on any existing version to get a template for creating new versions.
        </p>

        <h2 class="text-xl font-semibold mt-6 mb-2 text-gray-900 dark:text-white">Basic Structure</h2>
        <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg overflow-auto mb-4 text-gray-800 dark:text-white">
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

        <h2 class="text-xl font-semibold mt-6 mb-2 text-gray-900 dark:text-white">Character Stats</h2>
        <p class="mb-4 text-gray-700 dark:text-white">
            The <code
                class="bg-gray-200 dark:bg-gray-700 px-1 rounded text-gray-800 dark:text-white">character_stats</code>
            array contains statistics for each character in each language:
        </p>
        <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg overflow-auto mb-4 text-gray-800 dark:text-white">
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

        <h2 class="text-xl font-semibold mt-6 mb-2 text-gray-900 dark:text-white">Language Stats</h2>
        <p class="mb-4 text-gray-700 dark:text-white">
            The <code
                class="bg-gray-200 dark:bg-gray-700 px-1 rounded text-gray-800 dark:text-white">language_stats</code>
            array contains overall statistics for each language:
        </p>
        <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg overflow-auto mb-4 text-gray-800 dark:text-white">
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

        <h2 class="text-xl font-semibold mt-6 mb-2 text-gray-900 dark:text-white">Supported Languages</h2>
        <p class="mb-4 text-gray-700 dark:text-white">
            The <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded text-gray-800 dark:text-white">supported_languages</code>
            array lists all languages supported by this version:
        </p>
        <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg overflow-auto mb-4 text-gray-800 dark:text-white">
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

        <h2 class="text-xl font-semibold mt-6 mb-2 text-gray-900 dark:text-white">Notes</h2>
        <ul class="list-disc pl-6 mb-4 text-gray-700 dark:text-white">
            <li>All fields except <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded text-gray-800 dark:text-white">version</code>
                and <code
                    class="bg-gray-200 dark:bg-gray-700 px-1 rounded text-gray-800 dark:text-white">published_at</code>
                are optional
            </li>
            <li>If <code
                    class="bg-gray-200 dark:bg-gray-700 px-1 rounded text-gray-800 dark:text-white">published_at</code>
                is not provided, the current date and time will be used
            </li>
            <li>Platform fields (<code class="bg-gray-200 dark:bg-gray-700 px-1 rounded text-gray-800 dark:text-white">is_windows</code>,
                etc.) default to <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded text-gray-800 dark:text-white">false</code>
                if not provided
            </li>
            <li>The <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded text-gray-800 dark:text-white">character_stats</code>,
                <code
                    class="bg-gray-200 dark:bg-gray-700 px-1 rounded text-gray-800 dark:text-white">language_stats</code>,
                and <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded text-gray-800 dark:text-white">supported_languages</code>
                arrays can be empty or omitted
            </li>
        </ul>
    </x-filament::section>
</x-filament::page>

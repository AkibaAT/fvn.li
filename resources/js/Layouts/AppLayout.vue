<script setup lang="ts">
interface Props {
    title?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="mt-auto py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Horizontal Line -->
                <div
                    class="border-t border-gray-200 dark:border-gray-700"
                ></div>

                <!-- Footer Content -->
                <div
                    class="mt-8 grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3"
                >
                    <!-- About Section -->
                    <div>
                        <h3
                            class="mb-4 text-sm font-semibold text-gray-900 dark:text-gray-100"
                        >
                            About
                        </h3>
                        <div
                            class="space-y-3 text-sm text-gray-500 dark:text-gray-400"
                        >
                            <p>
                                The visibility of entries on this list is being
                                controlled by the
                                <a
                                    href="https://itch.io/c/2399304/fvnli-watchlist"
                                    class="text-blue-600 hover:underline dark:text-blue-400"
                                    target="_blank"
                                >
                                    itch.io collection</a
                                >.
                            </p>
                            <p>
                                Updates and ratings are pulled from the itch.io
                                feed every 15 minutes.
                            </p>
                            <p>
                                All source code for this page can be found on
                                <a
                                    href="https://github.com/AkibaAT/itch-web"
                                    class="inline-flex items-center space-x-1 text-blue-600 hover:underline dark:text-blue-400"
                                    target="_blank"
                                >
                                    <i class="fab fa-github"></i>
                                    <span>GitHub</span>
                                </a>
                                .
                            </p>
                        </div>
                    </div>
                    <!-- Contact Section -->
                    <div class="flex justify-center">
                        <div>
                            <h3
                                class="mb-4 text-sm font-semibold text-gray-900 dark:text-gray-100"
                            >
                                Contact
                            </h3>
                            <div class="flex flex-col space-y-3">
                                <a
                                    href="https://bsky.app/profile/akiba.games"
                                    class="flex items-center space-x-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                    target="_blank"
                                    title="Contact on Bluesky"
                                >
                                    <i
                                        class="fab fa-bluesky w-5 text-center"
                                    ></i>
                                    <span>@akiba.games</span>
                                </a>
                                <a
                                    href="https://discord.com/users/akiba.games"
                                    class="flex items-center space-x-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                    target="_blank"
                                    title="Contact on Discord"
                                >
                                    <i
                                        class="fab fa-discord w-5 text-center"
                                    ></i>
                                    <span>@akiba.games</span>
                                </a>
                                <a
                                    href="https://t.me/AkibaAT"
                                    class="flex items-center space-x-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                    target="_blank"
                                    title="Contact on Telegram"
                                >
                                    <i
                                        class="fab fa-telegram w-5 text-center"
                                    ></i>
                                    <span>@AkibaAT</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- Quick Access Section -->
                    <div>
                        <h3
                            class="mb-4 text-sm font-semibold text-gray-900 dark:text-gray-100"
                        >
                            Quick Access
                        </h3>
                        <div
                            class="space-y-3 text-sm text-gray-500 dark:text-gray-400"
                        >
                            <p>
                                Add the following link to your bookmarks to
                                quickly access ratings from any itch.io project
                                page, including those not listed on FVN.li:
                            </p>
                            <a
                                href="javascript:(function(){var%20e=window.location.hostname,t=window.location.pathname.split('/')[1];if(e.endsWith('.itch.io')%26%26e!=='itch.io'%26%26e!=='www.itch.io'%26%26t){window.open('https://fvn.li/by-url/'+window.location.origin+'/'+t,'_blank')}})();"
                                class="inline-flex items-center rounded bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-800 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                title="Drag to bookmarks bar"
                            >
                                <span>📘 FVN Ratings Link</span>
                            </a>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                Works on pages with URLs like
                                "creator.itch.io/project-name"
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

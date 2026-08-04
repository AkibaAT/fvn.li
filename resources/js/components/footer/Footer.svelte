<script lang="ts">
    import Container from '@/components/Container.svelte';
    import BugReportButton from '@/components/BugReportButton.svelte';
    import { Link } from '@inertiajs/svelte';

    const currentYear = new Date().getFullYear();
    const footerLinkClass =
        'text-gray-600 transition-colors hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:text-zinc-400 dark:hover:text-zinc-100';
    const footerContactLinkClass = `flex items-center gap-2 ${footerLinkClass}`;
</script>

<footer class="border-t border-gray-200 bg-white dark:border-zinc-800 dark:bg-[#0a0f1e]">
    <Container class="py-8">
        <h2 class="sr-only">Site information</h2>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <!-- Navigation -->
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-zinc-100">Navigation</h3>
                <ul class="space-y-2 text-sm">
                    <li>
                        <Link href={route('home')} class={footerLinkClass}>Home</Link>
                    </li>
                    <li>
                        <Link href={route('games.index')} class={footerLinkClass}>Browse Games</Link>
                    </li>
                    <li>
                        <Link href={route('lists.public')} class={footerLinkClass}>Public Lists</Link>
                    </li>
                    <li>
                        <Link href={route('ratings.index')} class={footerLinkClass}>Ratings</Link>
                    </li>
                </ul>
            </div>

            <!-- Quick Access -->
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-zinc-100">Quick Access</h3>
                <div class="space-y-2 text-sm text-gray-500 dark:text-zinc-400">
                    <p>Add this bookmarklet to quickly access ratings from itch.io project pages, including titles not listed on FVN.li:</p>
                    <a
                        href={"javascript:(function(){var%20e=window.location.hostname,t=window.location.pathname.split('/')[1];if(e.endsWith('.itch.io')%26%26e!=='itch.io'%26%26e!=='www.itch.io'%26%26t){window.open('https://fvn.li/by-url/'+window.location.origin+'/'+t,'_blank')}})();"}
                        class="inline-flex items-center rounded bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-800 transition-colors hover:bg-gray-200 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"
                        title="Drag to bookmarks bar"
                    >
                        FVN Ratings Link
                    </a>
                    <p class="text-xs text-gray-600 dark:text-zinc-400">Works on pages with URLs like "creator.itch.io/project-name"</p>
                </div>
            </div>

            <!-- Contact -->
            <div class="md:justify-self-end">
                <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-zinc-100">Contact</h3>
                <ul class="space-y-2 text-sm">
                    <li>
                        <a
                            href="https://github.com/AkibaAT/fvn.li"
                            class={footerContactLinkClass}
                            target="_blank"
                            rel="noopener"
                            title="View on GitHub"
                        >
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"
                                />
                            </svg>
                            <span>GitHub</span>
                        </a>
                    </li>
                    <li>
                        <a
                            href="https://bsky.app/profile/akiba.at"
                            class={footerContactLinkClass}
                            target="_blank"
                            rel="noopener"
                            title="Contact on Bluesky"
                        >
                            <i class="icon-bluesky w-5 text-center"></i>
                            <span>@akiba.at</span>
                        </a>
                    </li>
                    <li>
                        <a
                            href="https://discord.com/users/akiba.at"
                            class={footerContactLinkClass}
                            target="_blank"
                            rel="noopener"
                            title="Contact on Discord"
                        >
                            <i class="icon-discord w-5 text-center"></i>
                            <span>@akiba.at</span>
                        </a>
                    </li>
                    <li>
                        <a href="https://t.me/AkibaAT" class={footerContactLinkClass} target="_blank" rel="noopener" title="Contact on Telegram">
                            <i class="icon-telegram w-5 text-center"></i>
                            <span>@AkibaAT</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Bottom Section -->
        <div class="mt-8 border-t border-gray-200 pt-6 dark:border-zinc-800">
            <div class="flex flex-col items-center justify-between md:flex-row">
                <div class="mb-4 text-sm text-gray-500 md:mb-0 dark:text-zinc-400">
                    <p>&copy; 2023 - {currentYear} AkibaAT</p>
                </div>
                <div class="flex items-center space-x-4 text-sm">
                    <BugReportButton />
                    <span class="text-gray-300 dark:text-zinc-600">|</span>
                    <Link href={route('system.status')} class={footerLinkClass}>Status Page</Link>
                </div>
            </div>
        </div>
    </Container>
</footer>

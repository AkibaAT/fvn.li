<script lang="ts">
    import GithubIcon from '@/components/icons/Github.svelte';
    import BlueskyIcon from '@/components/icons/Bluesky.svelte';
    import DiscordIcon from '@/components/icons/Discord.svelte';
    import TelegramIcon from '@/components/icons/Telegram.svelte';
    import Container from '@/components/Container.svelte';
    import BugReportButton from '@/components/BugReportButton.svelte';
    import { Link } from '@inertiajs/svelte';

    const currentYear = new Date().getFullYear();
    const footerLinkClass = 'text-fg-muted transition-colors hover:text-fg';
    const footerContactLinkClass = `flex items-center gap-2 ${footerLinkClass}`;
</script>

<footer class="border-t border-border bg-surface">
    <Container class="py-8">
        <h2 class="sr-only">Site information</h2>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <div>
                <h3 class="mb-2 text-sm font-semibold text-fg">Navigation</h3>
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

            <div>
                <h3 class="mb-2 text-sm font-semibold text-fg">Quick Access</h3>
                <div class="space-y-2 text-sm text-fg-muted">
                    <p>Add this bookmarklet to quickly access ratings from itch.io project pages, including titles not listed on FVN.li:</p>
                    <a
                        href={"javascript:(function(){var%20e=window.location.hostname,t=window.location.pathname.split('/')[1];if(e.endsWith('.itch.io')%26%26e!=='itch.io'%26%26e!=='www.itch.io'%26%26t){window.open('https://fvn.li/by-url/'+window.location.origin+'/'+t,'_blank')}})();"}
                        class="inline-flex items-center rounded-md border border-border-strong bg-surface-alt px-3 py-1.5 text-sm font-medium text-fg transition-colors hover:border-fg"
                        title="Drag to bookmarks bar"
                    >
                        FVN Ratings Link
                    </a>
                    <p class="text-xs text-fg-muted">Works on pages with URLs like "creator.itch.io/project-name"</p>
                </div>
            </div>

            <div class="md:justify-self-end">
                <h3 class="mb-2 text-sm font-semibold text-fg">Contact</h3>
                <ul class="space-y-2 text-sm">
                    <li>
                        <a
                            href="https://github.com/AkibaAT/fvn.li"
                            class={footerContactLinkClass}
                            target="_blank"
                            rel="noopener"
                            title="View on GitHub"
                        >
                            <GithubIcon class="h-5 w-5" />
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
                            <BlueskyIcon class="h-5 w-5" />
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
                            <DiscordIcon class="h-5 w-5" />
                            <span>@akiba.at</span>
                        </a>
                    </li>
                    <li>
                        <a href="https://t.me/AkibaAT" class={footerContactLinkClass} target="_blank" rel="noopener" title="Contact on Telegram">
                            <TelegramIcon class="h-5 w-5" />
                            <span>@AkibaAT</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-8 border-t border-border pt-6">
            <div class="flex flex-col items-center justify-between md:flex-row">
                <div class="mb-4 text-sm text-fg-muted md:mb-0">
                    <p>&copy; 2023 - {currentYear} AkibaAT</p>
                </div>
                <div class="flex items-center space-x-4 text-sm">
                    <BugReportButton />
                    <span class="text-border">|</span>
                    <Link href={route('system.status')} class={footerLinkClass}>Status Page</Link>
                </div>
            </div>
        </div>
    </Container>
</footer>

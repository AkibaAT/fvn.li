import Container from '@/components/container';
import BugReportButton from '@/components/BugReportButton';
import {Link} from '@inertiajs/react';

export default function Footer() {
    const currentYear = new Date().getFullYear();

    return (
        <footer className="border-t border-[var(--color-ui-border)] bg-[var(--color-ui-surface)]">
            <Container className="py-12">
                <div className="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    {/* Brand Section */}
                    <div className="sm:col-span-2 lg:col-span-1">
                        <div className="mb-4 flex items-center gap-2.5">
                            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--color-brand-primary)] shadow-md shadow-black/10">
                                <svg
                                    className="h-5 w-5 text-white"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    strokeWidth={2}
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                                    />
                                </svg>
                            </div>
                            <span className="text-lg font-semibold text-[var(--color-ui-text)]">
                                FVN.li
                            </span>
                        </div>
                        <p className="mb-5 text-sm leading-relaxed text-[var(--color-ui-text-muted)]">
                            Your companion for discovering and tracking furry visual novels.
                        </p>
                        {/* Social Links */}
                        <div className="flex gap-3">
                            <a
                                href="https://github.com/AkibaAT/fvn.li"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--color-ui-surface-alt)] text-[var(--color-ui-text-muted)] transition-all hover:bg-[var(--color-surface-peach)] hover:text-[var(--color-ui-text)]"
                                title="View on GitHub"
                            >
                                <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    {/* Navigation */}
                    <div>
                        <h3 className="mb-4 text-sm font-medium text-[var(--color-ui-text)]">
                            Navigation
                        </h3>
                        <ul className="space-y-3">
                            <li>
                                <Link
                                    href={route('home')}
                                    className="text-sm text-[var(--color-ui-text-muted)] transition-colors hover:text-[var(--color-ui-text)]"
                                >
                                    Home
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href={route('games.index')}
                                    className="text-sm text-[var(--color-ui-text-muted)] transition-colors hover:text-[var(--color-ui-text)]"
                                >
                                    Browse Games
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href={route('lists.public')}
                                    className="text-sm text-[var(--color-ui-text-muted)] transition-colors hover:text-[var(--color-ui-text)]"
                                >
                                    Public Lists
                                </Link>
                            </li>
                        </ul>
                    </div>

                    {/* Contact */}
                    <div>
                        <h3 className="mb-4 text-sm font-medium text-[var(--color-ui-text)]">
                            Contact
                        </h3>
                        <ul className="space-y-3">
                            <li>
                                <a
                                    href="https://bsky.app/profile/akiba.at"
                                    className="flex items-center gap-2.5 text-sm text-[var(--color-ui-text-muted)] transition-colors hover:text-[var(--color-ui-text)]"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Contact on Bluesky"
                                >
                                    <i className="icon-bluesky w-4 text-center"></i>
                                    <span>@akiba.at</span>
                                </a>
                            </li>
                            <li>
                                <a
                                    href="https://discord.com/users/akiba.at"
                                    className="flex items-center gap-2.5 text-sm text-[var(--color-ui-text-muted)] transition-colors hover:text-[var(--color-ui-text)]"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Contact on Discord"
                                >
                                    <i className="icon-discord w-4 text-center"></i>
                                    <span>@akiba.at</span>
                                </a>
                            </li>
                            <li>
                                <a
                                    href="https://t.me/AkibaAT"
                                    className="flex items-center gap-2.5 text-sm text-[var(--color-ui-text-muted)] transition-colors hover:text-[var(--color-ui-text)]"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Contact on Telegram"
                                >
                                    <i className="icon-telegram w-4 text-center"></i>
                                    <span>@AkibaAT</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    {/* Quick Access */}
                    <div>
                        <h3 className="mb-4 text-sm font-medium text-[var(--color-ui-text)]">
                            Tools
                        </h3>
                        <p className="mb-4 text-sm leading-relaxed text-[var(--color-ui-text-muted)]">
                            Add this bookmarklet to quickly access ratings from any itch.io project page:
                        </p>
                        <a
                            href="javascript:(function(){var%20e=window.location.hostname,t=window.location.pathname.split('/')[1];if(e.endsWith('.itch.io')%26%26e!=='itch.io'%26%26e!=='www.itch.io'%26%26t){window.open('https://fvn.li/by-url/'+window.location.origin+'/'+t,'_blank')}})();"
                            className="inline-flex items-center gap-2 rounded-lg bg-[var(--color-brand-primary)] px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-[var(--color-brand-primary-dark)]"
                            title="Drag to bookmarks bar"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                            </svg>
                            FVN Ratings Link
                        </a>
                        <p className="mt-3 text-xs text-[var(--color-ui-text-muted)]">
                            Works on "creator.itch.io/project" pages
                        </p>
                    </div>
                </div>

                {/* Bottom Section */}
                <div className="mt-10 flex flex-col items-center justify-between gap-4 border-t border-[var(--color-ui-border)] pt-8 sm:flex-row">
                    <p className="text-sm text-[var(--color-ui-text-muted)]">
                        © 2023 - {currentYear} AkibaAT
                    </p>

                    <div className="flex items-center gap-4">
                        <BugReportButton />
                        <span className="text-[var(--color-ui-border)]">|</span>
                        <Link
                            href={route('system.status')}
                            className="text-sm text-[var(--color-ui-text-muted)] transition-colors hover:text-[var(--color-ui-text)]"
                        >
                            Status
                        </Link>
                    </div>
                </div>
            </Container>
        </footer>
    );
}

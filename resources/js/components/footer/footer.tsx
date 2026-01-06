import Container from '@/components/container';
import BugReportButton from '@/components/BugReportButton';
import {Link} from '@inertiajs/react';

export default function Footer() {
    const currentYear = new Date().getFullYear();

    return (
        <footer
            className="border-t border-gray-200/50 bg-white/70 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-900/70">
            <Container className="py-12">
                <div className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-4">
                    {/* Brand Section */}
                    <div className="lg:col-span-1">
                        <div className="mb-4 flex items-center space-x-3">
                            <div
                                className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 shadow-lg">
                                <span className="text-lg font-bold text-white">
                                    📖
                                </span>
                            </div>
                            <div>
                                <h2 className="text-xl font-bold text-blue-600 dark:text-blue-400">
                                    FVN.li
                                </h2>
                            </div>
                        </div>
                        <div className="mt-5 flex space-x-4">
                            {/* Social Links */}
                            <a
                                href="https://github.com/AkibaAT/fvn.li"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-gray-300"
                                title="View on GitHub"
                            >
                                <svg
                                    className="h-5 w-5"
                                    fill="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    {/* Navigation */}
                    <div>
                        <h3 className="mb-2 text-sm font-semibold text-gray-900 dark:text-white">
                            Navigation
                        </h3>
                        <ul className="space-y-3 text-sm">
                            <li>
                                <Link
                                    href={route('home')}
                                    className="text-gray-600 transition-colors hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                                >
                                    Home
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href={route('games.index')}
                                    className="text-gray-600 transition-colors hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                                >
                                    Browse Games
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href={route('lists.public')}
                                    className="text-gray-600 transition-colors hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                                >
                                    Public Lists
                                </Link>
                            </li>
                        </ul>
                    </div>

                    {/* Contact */}
                    <div>
                        <div>
                            <h3 className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Contact
                            </h3>
                            <ul className="space-y-3 text-sm">
                                <li>
                                    <a
                                        href="https://bsky.app/profile/akiba.at"
                                        className="flex items-center gap-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                        target="_blank"
                                        title="Contact on Bluesky"
                                    >
                                        <i className="icon-bluesky w-5 text-center"></i>
                                        <span>@akiba.at</span>
                                    </a>
                                </li>
                                <li>
                                    <a
                                        href="https://discord.com/users/akiba.at"
                                        className="flex items-center gap-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                        target="_blank"
                                        title="Contact on Discord"
                                    >
                                        <i className="icon-discord w-5 text-center"></i>
                                        <span>@akiba.at</span>
                                    </a>
                                </li>
                                <li>
                                    <a
                                        href="https://t.me/AkibaAT"
                                        className="flex items-center gap-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                        target="_blank"
                                        title="Contact on Telegram"
                                    >
                                        <i className="icon-telegram w-5 text-center"></i>
                                        <span>@AkibaAT</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    {/* Quick Access */}
                    <div>
                        <h3 className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            Quick Access
                        </h3>
                        <div className="space-y-3 text-sm text-gray-500 dark:text-gray-400">
                            <p>
                                Add the following link to your bookmarks to
                                quickly access ratings from any itch.io project
                                page, including those not listed on FVN.li:
                            </p>
                            <a
                                href="javascript:(function(){var%20e=window.location.hostname,t=window.location.pathname.split('/')[1];if(e.endsWith('.itch.io')%26%26e!=='itch.io'%26%26e!=='www.itch.io'%26%26t){window.open('https://fvn.li/by-url/'+window.location.origin+'/'+t,'_blank')}})();"
                                className="inline-flex items-center rounded bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-800 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                title="Drag to bookmarks bar"
                            >
                                <span>📘 FVN Ratings Link</span>
                            </a>
                            <p className="text-xs text-gray-600 dark:text-gray-400">
                                Works on pages with URLs like
                                "creator.itch.io/project-name"
                            </p>
                        </div>
                    </div>
                </div>

                {/* Bottom Section */}
                <div className="mt-12 border-t border-gray-200/50 pt-8 dark:border-gray-700/50">
                    <div className="flex flex-col items-center justify-between md:flex-row">
                        <div className="mb-4 text-sm text-gray-500 md:mb-0 dark:text-gray-400">
                            <p>© 2023 - {currentYear} AkibaAT</p>
                        </div>

                        {/* Platform Status & Bug Report */}
                        <div className="flex items-center space-x-4 text-sm">
                            <BugReportButton />
                            <span className="text-gray-300 dark:text-gray-600">|</span>
                            <Link
                                href={route('system.status')}
                                className="text-gray-500 transition-colors hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                            >
                                Status Page
                            </Link>
                        </div>
                    </div>
                </div>
            </Container>
        </footer>
    );
}

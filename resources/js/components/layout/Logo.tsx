import {Link} from '@inertiajs/react';

export default function Logo() {
    return (
        <Link
            href={route('home')}
            className="group flex items-center gap-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--color-brand-secondary)] focus:ring-offset-2"
            aria-label="FVN.li home page"
        >
            <div
                className="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--color-brand-primary)] shadow-md shadow-black/10 transition-all duration-200 group-hover:shadow-lg group-hover:shadow-black/20"
                aria-hidden="true"
            >
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
            <span className="text-lg font-bold tracking-tight text-[var(--color-ui-text)] transition-colors group-hover:text-[var(--color-brand-primary)]">
                FVN.li
            </span>
        </Link>
    );
}

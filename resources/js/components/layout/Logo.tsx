import {Link} from '@inertiajs/react';

export default function Logo() {
    return (
        <Link
            href={route('home')}
            className="group flex items-center space-x-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-lg"
            aria-label="FVN.li home page"
        >
            <div
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 shadow-lg transition-all duration-200 group-hover:scale-105 group-hover:shadow-xl"
                aria-hidden="true">
                <span className="text-lg font-bold text-white">
                    📖
                </span>
            </div>
            <div>
                <div className="text-xl font-bold text-blue-600 dark:text-blue-400">
                    FVN.li
                </div>
            </div>
        </Link>
    );
}
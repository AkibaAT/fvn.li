import {Link, usePage} from '@inertiajs/react';
import React, {useEffect, useRef, useState} from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_admin?: boolean;
}

interface InertiaPageProps {
    auth?: {
        user: User | null;
    };
}

export default function UserMenu() {
    const [showUserMenu, setShowUserMenu] = useState(false);
    const userMenuRef = useRef<HTMLDivElement>(null);
    const page = usePage();
    const user = (page.props as InertiaPageProps)?.auth?.user || null;

    // Close user menu when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (showUserMenu && userMenuRef.current) {
                if (!userMenuRef.current.contains(event.target as Node)) {
                    setShowUserMenu(false);
                }
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, [showUserMenu]);

    if (!user) {
        return (
            <div className="flex items-center space-x-2">
                <Link
                    href={route('login')}
                    className="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm transition-all duration-200 hover:bg-blue-700 hover:shadow-md"
                >
                    Login
                </Link>
            </div>
        );
    }

    return (
        <div className="relative" ref={userMenuRef}>
            <button
                onClick={() => setShowUserMenu(!showUserMenu)}
                className="flex items-center space-x-2 rounded-lg bg-gray-100 px-3 py-2 transition-colors duration-200 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                aria-expanded={showUserMenu}
                aria-haspopup="menu"
                aria-controls="user-menu"
            >
                {user.avatar ? (
                    <img
                        src={user.avatar}
                        alt={user.name}
                        className="h-6 w-6 rounded-full"
                        referrerPolicy="no-referrer"
                    />
                ) : (
                    <div
                        className="flex h-6 w-6 items-center justify-center rounded-full bg-green-500">
                        <span className="text-xs font-bold text-white">
                            {user.name?.charAt(0)?.toUpperCase() ?? 'U'}
                        </span>
                    </div>
                )}
                <span
                    className="hidden text-sm font-medium text-gray-700 sm:inline dark:text-gray-300">
                    {user.name}
                </span>
                <svg
                    className={`h-4 w-4 text-gray-500 transition-transform duration-200 ${showUserMenu ? 'rotate-180' : ''}`}
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M19 9l-7 7-7-7"
                    />
                </svg>
            </button>

            {/* User Dropdown Menu */}
            {showUserMenu && (
                <div
                    id="user-menu"
                    className="absolute right-0 z-50 mt-2 w-64 rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800"
                    role="menu"
                    aria-label="User menu">
                    <div className="p-4">
                        {/* User Info */}
                        <div className="mb-4 flex items-center space-x-3">
                            {user.avatar ? (
                                <img
                                    src={user.avatar}
                                    alt={user.name}
                                    className="h-10 w-10 rounded-full"
                                    referrerPolicy="no-referrer"
                                />
                            ) : (
                                <div
                                    className="flex h-10 w-10 items-center justify-center rounded-full bg-green-500">
                                    <span className="text-lg font-bold text-white">
                                        {user.name?.charAt(0)?.toUpperCase() ?? 'U'}
                                    </span>
                                </div>
                            )}
                            <div>
                                <div
                                    className="font-medium text-gray-900 dark:text-gray-100">
                                    {user.name}
                                </div>
                                {user.email && (
                                    <div
                                        className="text-sm text-gray-500 dark:text-gray-400">
                                        {user.email}
                                    </div>
                                )}
                            </div>
                        </div>

                        <hr className="mb-4 border-gray-200 dark:border-gray-700"/>

                        {/* Menu Items */}
                        <div className="space-y-2" role="none">
                            <Link
                                href={route('dashboard')}
                                className="flex w-full items-center space-x-2 rounded-lg bg-indigo-600 px-3 py-2 text-white transition-all duration-200 hover:bg-indigo-700 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                onClick={() => setShowUserMenu(false)}
                                role="menuitem"
                            >
                                <svg
                                    className="h-5 w-5 drop-shadow-sm"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                    />
                                </svg>
                                <span className="font-medium">
                                    Dashboard
                                </span>
                            </Link>

                            <Link
                                href={route('lists.index')}
                                className="flex w-full items-center space-x-2 rounded-lg bg-blue-600 px-3 py-2 text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                onClick={() => setShowUserMenu(false)}
                                role="menuitem"
                            >
                                <svg
                                    className="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                    />
                                </svg>
                                <span>
                                    My VN Lists
                                </span>
                            </Link>

                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="flex w-full items-center space-x-2 rounded-lg bg-red-600 px-3 py-2 text-white transition-colors hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                onClick={() => setShowUserMenu(false)}
                                role="menuitem"
                            >
                                <svg
                                    className="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                    />
                                </svg>
                                <span>
                                    Sign Out
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
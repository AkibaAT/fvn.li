import ItchioIcon from '@/components/icons/itchio';
import SteamIcon from '@/components/icons/steam';
import {User} from '@/types';
import {router} from '@inertiajs/react';
import React, {useEffect, useRef} from 'react';

interface AuthWidgetProps {
    user?: User;
}

interface SocialButtonProps {
    href: string;
    icon: React.ReactNode;
    children: React.ReactNode;
}

function SocialButton({href, icon, children}: SocialButtonProps) {
    return (
        <a
            href={href}
            className="flex w-full items-center rounded-lg border border-gray-300 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
        >
            {icon}
            <span>{children}</span>
        </a>
    );
}

function DiscordIcon() {
    return <i className="icon-discord mr-3 h-5 w-5 text-indigo-500"></i>;
}

function GoogleIcon() {
    return (
        <svg className="mr-3 h-5 w-5" viewBox="0 0 24 24">
            <path
                fill="#4285F4"
                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
            />
            <path
                fill="#34A853"
                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
            />
            <path
                fill="#FBBC05"
                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
            />
            <path
                fill="#EA4335"
                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
            />
        </svg>
    );
}

function ItchioInlineIcon() {
    return <ItchioIcon className="mr-3 h-5 w-5 text-itchio"/>;
}

function SteamInlineIcon() {
    return <SteamIcon className="mr-3 h-5 w-5"/>;
}

function TelegramIcon() {
    return <i className="icon-telegram mr-3 h-5 w-5 text-blue-500"></i>;
}

export function AuthWidget({user}: AuthWidgetProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);

    const openDialog = () => {
        if (dialogRef.current) {
            dialogRef.current.showModal();
        }
    };

    const closeDialog = () => {
        if (dialogRef.current) {
            dialogRef.current.close();
        }
    };

    const handleLogout = () => {
        router.post(
            route('logout'),
            {},
            {
                onSuccess: () => {
                    closeDialog();
                    window.location.reload();
                },
            },
        );
    };

    useEffect(() => {
        const dialog = dialogRef.current;
        if (dialog) {
            const handleClickOutside = (e: MouseEvent) => {
                if (e.target === dialog) {
                    closeDialog();
                }
            };

            dialog.addEventListener('click', handleClickOutside);
            return () =>
                dialog.removeEventListener('click', handleClickOutside);
        }
    }, []);

    return (
        <>
            <button
                onClick={openDialog}
                className="flex items-center gap-2 rounded-lg bg-gray-100 px-4 py-2 text-gray-800 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
            >
                {user ? (
                    <div className="flex items-center gap-2">
                        {user.avatar ? (
                            <img
                                src={user.avatar}
                                alt={user.name}
                                className="h-6 w-6 rounded-full"
                            />
                        ) : (
                            <div
                                className="flex h-6 w-6 items-center justify-center rounded-full bg-blue-500 text-xs font-bold text-white">
                                {user.name.charAt(0)}
                            </div>
                        )}
                        <span className="hidden sm:inline">{user.name}</span>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            className="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </div>
                ) : (
                    <>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            className="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                            />
                        </svg>
                        <span>Login</span>
                    </>
                )}
            </button>

            <dialog
                ref={dialogRef}
                className="m-auto w-full max-w-sm rounded-lg bg-white p-6 shadow-xl backdrop:backdrop-blur-md dark:bg-gray-800 dark:text-gray-100"
            >
                <div className="mb-4 flex items-baseline justify-between">
                    <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">
                        {user ? 'Account' : 'Sign in with'}
                    </h2>
                    <button
                        onClick={closeDialog}
                        className="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                    >
                        <span className="sr-only">Close</span>
                        <svg
                            className="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            strokeWidth="1.5"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                {user ? (
                    <div className="space-y-4 py-4">
                        <div className="flex items-center gap-3">
                            {user.avatar ? (
                                <img
                                    src={user.avatar}
                                    alt={user.name}
                                    className="h-10 w-10 rounded-full"
                                />
                            ) : (
                                <div
                                    className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-500 text-lg font-bold text-white">
                                    {user.name.charAt(0)}
                                </div>
                            )}
                            <div>
                                <div className="font-medium text-gray-900 dark:text-gray-100">
                                    {user.name}
                                </div>
                                {user.email && (
                                    <div className="text-sm text-gray-500 dark:text-gray-400">
                                        {user.email}
                                    </div>
                                )}
                            </div>
                        </div>

                        <hr className="border-gray-200 dark:border-gray-700"/>

                        <a
                            href={route('dashboard')}
                            className="mb-3 flex w-full items-center justify-center gap-2 rounded-lg bg-gray-100 px-4 py-2 text-gray-900 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                className="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                />
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                            <span>User Dashboard</span>
                        </a>

                        <a
                            href={route('lists.index')}
                            className="mb-3 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                className="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                />
                            </svg>
                            <span>My VN Lists</span>
                        </a>

                        <button
                            onClick={handleLogout}
                            className="flex w-full items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-white transition-colors hover:bg-red-700"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                className="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                />
                            </svg>
                            <span>Sign Out</span>
                        </button>
                    </div>
                ) : (
                    <div className="space-y-3 py-4">
                        <SocialButton
                            href={route('auth.redirect', 'discord')}
                            icon={<DiscordIcon/>}
                        >
                            Discord
                        </SocialButton>

                        <SocialButton
                            href={route('auth.redirect', 'google')}
                            icon={<GoogleIcon/>}
                        >
                            Google
                        </SocialButton>

                        <SocialButton
                            href={route('auth.redirect', 'itchio')}
                            icon={<ItchioInlineIcon/>}
                        >
                            itch.io
                        </SocialButton>

                        <SocialButton
                            href={route('auth.redirect', 'steam')}
                            icon={<SteamInlineIcon/>}
                        >
                            Steam
                        </SocialButton>

                        <SocialButton
                            href={route('auth.redirect', 'telegram')}
                            icon={<TelegramIcon/>}
                        >
                            Telegram
                        </SocialButton>
                    </div>
                )}

                <div className="mt-6 flex justify-end">
                    <button
                        onClick={closeDialog}
                        type="button"
                        className="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700"
                    >
                        Close
                    </button>
                </div>
            </dialog>
        </>
    );
}

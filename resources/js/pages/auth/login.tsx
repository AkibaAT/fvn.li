import ItchioIcon from '@/components/icons/itchio';
import SteamIcon from '@/components/icons/steam';
import {Head, Link} from '@inertiajs/react';
import React from 'react';

interface LoginProps {
    metaTags?: {
        title?: string;
    };
}

// Social login button component
interface SocialButtonProps {
    href: string;
    icon: React.ReactNode;
    children: React.ReactNode;
    className?: string;
}

function SocialButton({
                          href,
                          icon,
                          children,
                          className = '',
                      }: SocialButtonProps) {
    return (
        <a
            href={href}
            className={`flex w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700 ${className}`}
        >
            {icon}
            <span>{children}</span>
        </a>
    );
}

// Icon components
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
    return <i className="icon-telegram mr-2 h-5 w-5 text-blue-500"></i>;
}

export default function Login({metaTags}: LoginProps) {
    return (
        <>
            <Head title={metaTags?.title || 'Log in'}/>

            <div className="py-12">
                <div className="mx-auto max-w-md sm:px-6 lg:px-8">
                    <div
                        className="overflow-hidden border border-gray-200/50 bg-white/70 shadow-lg backdrop-blur-xl sm:rounded-2xl dark:border-gray-700/50 dark:bg-gray-800/70">
                        <div className="p-6 text-gray-900 dark:text-gray-100">
                            <div className="mb-6 text-center">
                                <h1 className="mb-2 text-2xl font-bold text-blue-600">
                                    Welcome to FVN.li
                                </h1>
                                <p className="text-gray-600 dark:text-gray-400">
                                    Log in to manage your visual novel
                                    collections
                                </p>
                            </div>

                            <div className="space-y-3">
                                <SocialButton
                                    href={route('auth.redirect', 'discord')}
                                    icon={<DiscordIcon/>}
                                >
                                    Continue with Discord
                                </SocialButton>

                                <SocialButton
                                    href={route('auth.redirect', 'google')}
                                    icon={<GoogleIcon/>}
                                >
                                    Continue with Google
                                </SocialButton>

                                <SocialButton
                                    href={route('auth.redirect', 'itchio')}
                                    icon={<ItchioInlineIcon/>}
                                >
                                    Continue with itch.io
                                </SocialButton>

                                <SocialButton
                                    href={route('auth.redirect', 'steam')}
                                    icon={<SteamInlineIcon/>}
                                >
                                    Continue with Steam
                                </SocialButton>

                                <SocialButton
                                    href={route('auth.redirect', 'telegram')}
                                    icon={<TelegramIcon/>}
                                >
                                    Continue with Telegram
                                </SocialButton>
                            </div>

                            <div className="mt-6 text-center">
                                <Link
                                    href={route('home')}
                                    className="text-sm text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                >
                                    ← Back to home
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

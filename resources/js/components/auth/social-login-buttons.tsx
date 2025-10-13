import ItchioIcon from '@/components/icons/itchio';
import SteamIcon from '@/components/icons/steam';
import React from 'react';

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
                fill="currentColor"
                d="M21.35 11.1h-9.17v2.73h6.5c-.33 3.8-3.5 5.44-6.5 5.44C8.36 19.27 5 16.25 5 12c0-4.1 3.2-7.27 7.2-7.27c3.09 0 4.9 1.97 4.9 1.97L19 4.72S16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12c0 5.05 4.13 10 10.22 10c5.35 0 9.25-3.67 9.25-9.09c0-1.15-.15-1.81-.15-1.81Z"
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

export function SocialLoginButtons() {
    return (
        <div className="space-y-3">
            <SocialButton href="/auth/discord/redirect" icon={<DiscordIcon/>}>
                Continue with Discord
            </SocialButton>

            <SocialButton href="/auth/google/redirect" icon={<GoogleIcon/>}>
                Continue with Google
            </SocialButton>

            <SocialButton
                href="/auth/itchio/redirect"
                icon={<ItchioInlineIcon/>}
            >
                Continue with itch.io
            </SocialButton>

            <SocialButton
                href="/auth/steam/redirect"
                icon={<SteamInlineIcon/>}
            >
                Continue with Steam
            </SocialButton>

            <SocialButton href="/auth/telegram" icon={<TelegramIcon/>}>
                Continue with Telegram
            </SocialButton>
        </div>
    );
}

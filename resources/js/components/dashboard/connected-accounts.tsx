import ItchioIcon from '@/components/icons/itchio';
import {SocialAccount, User} from '@/types';
import {toast} from '@/utils/toast';
import {useState} from 'react';

interface ConnectedAccountsProps {
    user: User;
    connectedProviders: string[];
    socialAccounts: Record<string, SocialAccount>;
}

const PROVIDERS = {
    discord: {
        name: 'Discord',
        icon: () => (
            <i className="icon-discord h-6 w-6 text-xl text-indigo-500"></i>
        ),
    },
    google: {
        name: 'Google',
        icon: () => (
            <svg className="h-6 w-6" viewBox="0 0 24 24">
                <path
                    fill="currentColor"
                    d="M21.35 11.1h-9.17v2.73h6.5c-.33 3.8-3.5 5.44-6.5 5.44C8.36 19.27 5 16.25 5 12c0-4.1 3.2-7.27 7.2-7.27c3.09 0 4.9 1.97 4.9 1.97L19 4.72S16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12c0 5.05 4.13 10 10.22 10c5.35 0 9.25-3.67 9.25-9.09c0-1.15-.15-1.81-.15-1.81Z"
                />
            </svg>
        ),
    },
    itchio: {
        name: 'itch.io',
        icon: () => <ItchioIcon className="h-6 w-6 text-itchio"/>,
    },
    steam: {
        name: 'Steam',
        icon: () => (
            <svg className="h-6 w-6" viewBox="0 0 65 65">
                <path
                    d="M31.959 64c17.673 0 32-14.327 32-32s-14.327-32-32-32C15.001 0 1.124 13.193.028 29.874c2.074 3.477 2.879 5.628 1.275 11.328C5.259 54.386 17.488 64 31.959 64z"
                    fill="var(--color-steam-primary)"
                />
                <path
                    d="M30.31 23.985l.003.158-7.83 11.375c-1.268-.058-2.54.165-3.748.662a8.14 8.14 0 0 0-1.498.8L.042 29.893s-.398 6.546 1.26 11.424l12.156 5.016c.6 2.728 2.48 5.12 5.242 6.27a8.88 8.88 0 0 0 11.603-4.782 8.89 8.89 0 0 0 .684-3.656L42.18 36.16l.275.005c6.705 0 12.155-5.466 12.155-12.18s-5.44-12.16-12.155-12.174c-6.702 0-12.155 5.46-12.155 12.174zm-1.88 23.05c-1.454 3.5-5.466 5.147-8.953 3.694a6.84 6.84 0 0 1-3.524-3.362l3.957 1.64a5.04 5.04 0 0 0 6.591-2.719 5.05 5.05 0 0 0-2.715-6.601l-4.1-1.695c1.578-.6 3.372-.62 5.05.077 1.7.703 3 2.027 3.696 3.72s.692 3.56-.01 5.246M42.466 32.1a8.12 8.12 0 0 1-8.098-8.113a8.12 8.12 0 0 1 8.098-8.111a8.12 8.12 0 0 1 8.1 8.111a8.12 8.12 0 0 1-8.1 8.113m-6.068-8.126a6.09 6.09 0 0 1 6.08-6.095c3.355 0 6.084 2.73 6.084 6.095a6.09 6.09 0 0 1-6.084 6.093a6.09 6.09 0 0 1-6.081-6.093z"
                    fill="var(--color-steam-secondary)"
                />
            </svg>
        ),
    },
    telegram: {
        name: 'Telegram',
        icon: () => (
            <i className="icon-telegram h-6 w-6 text-xl text-blue-500"></i>
        ),
    },
};

export function ConnectedAccounts({
                                      connectedProviders,
                                      socialAccounts,
                                  }: ConnectedAccountsProps) {
    const [providers, setProviders] = useState<string[]>(
        connectedProviders || [],
    );
    const [accounts, setAccounts] = useState<Record<string, SocialAccount>>(
        socialAccounts || {},
    );
    const handleDisconnect = async (provider: string) => {
        if (
            !confirm(
                `Are you sure you want to disconnect your ${PROVIDERS[provider as keyof typeof PROVIDERS]?.name} account?`,
            )
        ) {
            return;
        }

        try {
            const response = await fetch(
                route('user.disconnect', {provider}),
                {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                    },
                    credentials: 'same-origin',
                },
            );

            if (response.ok) {
                const data = await response.json().catch(() => ({}));
                toast.success(
                    data?.message ||
                    `${PROVIDERS[provider as keyof typeof PROVIDERS]?.name} account disconnected successfully.`,
                );
                // Update UI locally instead of full reload
                setProviders((prev) => prev.filter((p) => p !== provider));
                setAccounts((prev) => {
                    const next = {...prev};
                    delete next[provider];
                    return next;
                });
            } else {
                toast.error('Failed to disconnect account.');
            }
        } catch (error) {
            console.error('Error disconnecting account:', error);
            toast.error('An error occurred while disconnecting the account.');
        }
    };

    const handleConnect = (provider: string) => {
        if (
            !confirm(
                `If an account already exists with this ${PROVIDERS[provider as keyof typeof PROVIDERS]?.name} login, it will be merged into your current account. This action cannot be undone. Continue?`,
            )
        ) {
            return;
        }

        // Submit a POST form (route only accepts POST)
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = route('user.merge', {provider});

        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') || '';
        form.appendChild(csrfToken);

        document.body.appendChild(form);
        form.submit();
    };

    return (
        <div
            className="rounded-2xl border border-gray-200/50 bg-white/70 shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
            <div className="p-6">
                <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                    Connected Accounts
                </h2>

                <div className="grid grid-cols-1 gap-4">
                    {Object.entries(PROVIDERS).map(([provider, config]) => {
                        const isConnected = providers.includes(provider);
                        const accountData = accounts[provider];

                        return (
                            <div
                                key={provider}
                                className="rounded-lg border p-4 transition-colors hover:bg-gray-50/50 dark:border-gray-700 dark:hover:bg-gray-700/30"
                            >
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        {config.icon()}
                                        <span className="font-medium text-gray-900 dark:text-white">
                                            {config.name}
                                        </span>
                                    </div>

                                    {isConnected ? (
                                        <div className="flex items-center gap-2">
                                            {accountData && (
                                                <div className="flex items-center gap-2">
                                                    {accountData.avatar && (
                                                        <img
                                                            src={
                                                                accountData.avatar
                                                            }
                                                            alt={`${config.name} avatar`}
                                                            className="h-6 w-6 rounded-full"
                                                        />
                                                    )}
                                                    {accountData.display_name && (
                                                        <span className="text-sm text-gray-600 dark:text-gray-400">
                                                            {
                                                                accountData.display_name
                                                            }
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                            <span className="text-sm font-medium text-green-500">
                                                Connected
                                            </span>
                                            <button
                                                onClick={() =>
                                                    handleDisconnect(provider)
                                                }
                                                className="ml-2 text-red-500 transition-colors hover:text-red-600"
                                                title={`Unlink ${config.name} account`}
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
                                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                    ) : (
                                        <button
                                            onClick={() =>
                                                handleConnect(provider)
                                            }
                                            className="text-sm font-medium text-blue-500 transition-colors hover:text-blue-600"
                                        >
                                            Connect
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

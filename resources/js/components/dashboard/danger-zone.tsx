import {toast} from '@/utils/toast';
import React, {useState} from 'react';

export function DangerZone() {
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDeleteAccount = async (e: React.FormEvent) => {
        e.preventDefault();

        if (
            !confirm(
                'Are you sure you want to delete your account? This action cannot be undone.',
            )
        ) {
            return;
        }

        // Double confirmation for account deletion
        if (
            !confirm(
                'This will permanently delete all your data, including your lists, progress, and account information. Are you absolutely sure?',
            )
        ) {
            return;
        }

        setIsDeleting(true);

        try {
            const response = await fetch(route('user.delete'), {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                // Account deleted successfully, redirect to home
                window.location.href = '/';
            } else {
                const data = await response.json();
                toast.error(data.message || 'Failed to delete account.');
            }
        } catch (error) {
            console.error('Error deleting account:', error);
            toast.error('An error occurred while deleting your account.');
        } finally {
            setIsDeleting(false);
        }
    };

    return (
        <div
            className="rounded-2xl border border-red-200/50 bg-white/70 shadow-lg backdrop-blur-xl dark:border-red-800/50 dark:bg-gray-800/70">
            <div className="p-6">
                <h2 className="mb-4 text-lg font-semibold text-red-600 dark:text-red-500">
                    Danger Zone
                </h2>

                <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                    <h3 className="mb-2 font-medium text-red-800 dark:text-red-400">
                        Delete Account
                    </h3>
                    <p className="mb-4 text-sm text-red-700 dark:text-red-300">
                        Once you delete your account, there is no going back.
                        Please be certain.
                    </p>

                    <form onSubmit={handleDeleteAccount}>
                        <button
                            type="submit"
                            disabled={isDeleting}
                            className="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {isDeleting ? (
                                <>
                                    <div className="h-4 w-4 animate-spin rounded-full border-b-2 border-white"></div>
                                    Deleting...
                                </>
                            ) : (
                                <>
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
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                        />
                                    </svg>
                                    Delete Account
                                </>
                            )}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}

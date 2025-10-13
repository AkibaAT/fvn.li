import React from 'react';

interface FlashMessagesProps {
    message?: string;
    error?: string;
}

export default function FlashMessages({message, error}: FlashMessagesProps) {
    return (
        <>
            {message && (
                <div className="mx-4 mt-4">
                    <div className="rounded-xl border border-green-200 bg-green-50 px-6 py-4 text-green-800 shadow-sm backdrop-blur-sm dark:border-green-700 dark:bg-green-900/20 dark:text-green-200">
                        <div className="flex items-center space-x-3">
                            <span className="text-xl text-green-500">
                                ✅
                            </span>
                            <span className="font-medium">
                                {message}
                            </span>
                        </div>
                    </div>
                </div>
            )}

            {error && (
                <div className="mx-4 mt-4">
                    <div className="rounded-xl border border-red-200 bg-red-50 px-6 py-4 text-red-800 shadow-sm backdrop-blur-sm dark:border-red-700 dark:bg-red-900/20 dark:text-red-200">
                        <div className="flex items-center space-x-3">
                            <span className="text-xl text-red-500">
                                ❌
                            </span>
                            <span className="font-medium">
                                {error}
                            </span>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
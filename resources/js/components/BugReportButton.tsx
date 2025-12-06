import {usePage} from '@inertiajs/react';
import React, {useState, useEffect, useRef} from 'react';
import {notify} from '@/components/toast';

interface User {
    id: number;
    name: string;
}

interface InertiaPageProps {
    auth?: {
        user: User | null;
    };
}

export default function BugReportButton() {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const closeButtonRef = useRef<HTMLButtonElement>(null);
    const openerRef = useRef<HTMLElement | null>(null);

    const [isOpen, setIsOpen] = useState(false);
    const [description, setDescription] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [pageInfo, setPageInfo] = useState({
        url: '',
        title: '',
        params: {} as Record<string, string>,
    });

    const page = usePage();
    const user = (page.props as InertiaPageProps)?.auth?.user || null;

    // Handle dialog open/close
    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        if (isOpen) {
            // Remember the current focused element
            openerRef.current = (document.activeElement as HTMLElement) || null;
            if (!dialog.open) dialog.showModal();
            // Focus the close button
            requestAnimationFrame(() => {
                closeButtonRef.current?.focus();
            });
        } else if (dialog.open) {
            dialog.close();
            // Return focus to opener
            openerRef.current?.focus();
        }
    }, [isOpen]);

    // Handle native dialog close event (ESC key, etc.)
    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        const handleClose = () => {
            setIsOpen(false);
            setDescription('');
            openerRef.current?.focus?.();
            openerRef.current = null;
        };

        dialog.addEventListener('close', handleClose);
        return () => dialog.removeEventListener('close', handleClose);
    }, []);

    // Capture page info when modal opens
    useEffect(() => {
        if (isOpen) {
            const url = new URL(window.location.href);
            const params: Record<string, string> = {};
            url.searchParams.forEach((value, key) => {
                params[key] = value;
            });

            setPageInfo({
                url: window.location.href,
                title: document.title,
                params,
            });
        }
    }, [isOpen]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!user) {
            notify('You must be logged in to submit a bug report.', 'error');
            return;
        }

        if (description.trim().length < 10) {
            notify('Please provide a more detailed description (at least 10 characters).', 'error');
            return;
        }

        setIsSubmitting(true);

        try {
            const response = await fetch(route('react-api.bug-reports.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    page_url: pageInfo.url,
                    page_title: pageInfo.title,
                    description: description.trim(),
                    request_parameters: pageInfo.params,
                }),
            });

            const data = await response.json();

            if (data.success) {
                notify(data.message, 'success');
                setDescription('');
                setIsOpen(false);
            } else {
                notify(data.message || 'Failed to submit bug report.', 'error');
            }
        } catch {
            notify('An error occurred while submitting the bug report.', 'error');
        } finally {
            setIsSubmitting(false);
        }
    };

    // Don't render during SSR
    if (typeof window === 'undefined') {
        return null;
    }

    return (
        <>
            {/* Bug Report Button */}
            <button
                onClick={() => setIsOpen(true)}
                className="flex items-center gap-2 text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                aria-label="Report a bug"
                title="Report a bug"
            >
                <svg
                    className="h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <span>Report a Bug</span>
            </button>

            {/* Bug Report Dialog */}
            <dialog
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby="bug-report-title"
                className="m-auto w-full max-w-lg rounded-lg border border-gray-200 bg-white p-0 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800"
                onClick={(e) => {
                    if (e.target === e.currentTarget) setIsOpen(false);
                }}
            >
                {/* Header */}
                <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h2
                        id="bug-report-title"
                        className="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Report a Bug
                    </h2>
                    <button
                        ref={closeButtonRef}
                        type="button"
                        onClick={() => setIsOpen(false)}
                        className="rounded-md text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:hover:text-gray-300"
                        aria-label="Close dialog"
                    >
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Content */}
                <form onSubmit={handleSubmit} className="px-6 py-4">
                    {!user && (
                        <div className="mb-4 rounded-lg bg-amber-50 p-4 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                            <p className="text-sm">
                                You must be logged in to submit a bug report. Please{' '}
                                <a
                                    href={route('login')}
                                    className="font-medium underline hover:no-underline"
                                >
                                    log in
                                </a>{' '}
                                to continue.
                            </p>
                        </div>
                    )}

                    <div className="mb-4">
                        <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Page URL
                        </label>
                        <input
                            type="text"
                            value={pageInfo.url}
                            readOnly
                            className="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400"
                        />
                    </div>

                    {Object.keys(pageInfo.params).length > 0 && (
                        <div className="mb-4">
                            <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Page Parameters
                            </label>
                            <div className="rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700">
                                {Object.entries(pageInfo.params).map(([key, value]) => (
                                    <div key={key} className="text-gray-600 dark:text-gray-400">
                                        <span className="font-medium">{key}:</span> {value}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="mb-4">
                        <label
                            htmlFor="bug-description"
                            className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Describe the issue <span className="text-red-500">*</span>
                        </label>
                        <textarea
                            id="bug-description"
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            placeholder="Please describe what happened, what you expected to happen, and any steps to reproduce the issue..."
                            rows={5}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                            required
                            minLength={10}
                            disabled={!user}
                        />
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Minimum 10 characters. Be as specific as possible.
                        </p>
                    </div>

                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setIsOpen(false)}
                            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={isSubmitting || !user}
                            className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {isSubmitting ? 'Submitting...' : 'Submit Report'}
                        </button>
                    </div>
                </form>
            </dialog>
        </>
    );
}

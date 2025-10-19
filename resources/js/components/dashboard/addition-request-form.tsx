import {toast} from '@/utils/toast';
import React, {useState} from 'react';

interface SubmissionResult {
    success_count: number;
    duplicate_count: number;
    invalid_count: number;
    already_exists_count?: number;
    errors: string[];
}

export function AdditionRequestForm() {
    const [urls, setUrls] = useState('');
    const [loading, setLoading] = useState(false);
    const [showSuccessMessage, setShowSuccessMessage] = useState(false);
    const [submissionResults, setSubmissionResults] =
        useState<SubmissionResult | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const submitRequests = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        if (!urls.trim()) {
            setErrors({urls: 'Please enter at least one game URL.'});
            setLoading(false);
            return;
        }

        try {
            const response = await fetch(
                route('react-api.dashboard.addition-requests.submit'),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({urls}),
                },
            );

            const data = await response.json();

            if (data.success) {
                // API returns `result`
                const results = data.result || data.results;
                setSubmissionResults(results);
                if (results?.success_count > 0) {
                    setShowSuccessMessage(true);
                    setUrls('');

                    // Dispatch event to refresh request lists
                    window.dispatchEvent(
                        new CustomEvent('addition-requests-updated'),
                    );
                }

                if (results?.success_count > 0) {
                    toast.success(
                        `Successfully submitted ${results.success_count} request(s)!`,
                    );
                }

                if (results?.errors && results.errors.length > 0) {
                    results.errors.forEach((error: string) => {
                        toast.error(error);
                    });
                }
            } else {
                // Show field-level and general errors
                if (data.errors && typeof data.errors === 'object') {
                    const values = Object.values<string | string[]>(
                        data.errors,
                    ).flat();
                    if (values.length) {
                        values.forEach((msg) => toast.error(String(msg)));
                    }
                    // Only set field-level errors if keyed object contains `urls`
                    if (!Array.isArray(data.errors) && 'urls' in data.errors) {
                        setErrors({
                            urls: Array.isArray(data.errors.urls)
                                ? data.errors.urls[0]
                                : data.errors.urls,
                        });
                    }
                }
                if (data.message) {
                    toast.error(data.message);
                }
            }
        } catch (error) {
            console.error('Error submitting requests:', error);
            toast.error('An error occurred while submitting requests.');
        } finally {
            setLoading(false);
        }
    };

    const clearForm = () => {
        setUrls('');
        setShowSuccessMessage(false);
        setSubmissionResults(null);
        setErrors({});
    };

    return (
        <div
            className="rounded-2xl border border-gray-200/50 bg-white/70 shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
            <div className="p-6">
                <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                    Request Game Additions
                </h2>

                <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Submit game URLs (itch.io, Steam, or other platforms) of visual novels you'd like to see added
                    to the database. You can submit multiple URLs, one per line.
                </p>

                {showSuccessMessage && submissionResults && (
                    <div
                        className="mb-4 rounded-lg border border-green-200 bg-green-100 p-4 dark:border-green-800 dark:bg-green-900/20">
                        <div className="text-green-700 dark:text-green-300">
                            <p className="font-medium">
                                Requests Submitted Successfully!
                            </p>
                            <ul className="mt-2 space-y-1 text-sm">
                                {submissionResults.success_count > 0 && (
                                    <li>
                                        ✓ {submissionResults.success_count} new
                                        request(s) submitted
                                    </li>
                                )}
                                {submissionResults.duplicate_count > 0 && (
                                    <li>
                                        ℹ {submissionResults.duplicate_count}{' '}
                                        URL(s) already requested by you
                                    </li>
                                )}
                                {submissionResults.already_exists_count &&
                                    submissionResults.already_exists_count >
                                    0 && (
                                        <li>
                                            ℹ{' '}
                                            {
                                                submissionResults.already_exists_count
                                            }{' '}
                                            game(s) already exist on the site
                                        </li>
                                    )}
                                {submissionResults.invalid_count > 0 && (
                                    <li>
                                        ⚠ {submissionResults.invalid_count}{' '}
                                        invalid URL(s) skipped
                                    </li>
                                )}
                            </ul>
                        </div>
                    </div>
                )}

                {submissionResults?.errors &&
                    submissionResults.errors.length > 0 && (
                        <div
                            className="mb-4 rounded-lg border border-red-200 bg-red-100 p-4 dark:border-red-800 dark:bg-red-900/20">
                            <p className="mb-2 font-medium text-red-800 dark:text-red-400">
                                Some errors occurred:
                            </p>
                            <ul className="space-y-1 text-sm text-red-700 dark:text-red-300">
                                {submissionResults.errors.map((error, idx) => (
                                    <li key={idx}>• {error}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                <form onSubmit={submitRequests} className="space-y-4">
                    <div>
                        <label
                            htmlFor="urls"
                            className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Game URLs
                        </label>
                        <textarea
                            id="urls"
                            value={urls}
                            onChange={(e) => setUrls(e.target.value)}
                            rows={6}
                            className={`w-full rounded-md border bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white ${
                                errors.urls
                                    ? 'border-red-300 dark:border-red-600'
                                    : 'border-gray-300 dark:border-gray-600'
                            }`}
                            placeholder="https://developer.itch.io/game-name&#10;https://store.steampowered.com/app/123456/Game_Name/&#10;https://example.com/game&#10;..."
                        />
                        {errors.urls && (
                            <p className="mt-1 text-sm text-red-600 dark:text-red-400">
                                {errors.urls}
                            </p>
                        )}
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Enter one URL per line. Maximum 50 URLs per
                            submission.
                        </p>
                    </div>

                    <div className="flex gap-3">
                        <button
                            type="submit"
                            disabled={loading || !urls.trim()}
                            className="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-white shadow-md transition-all duration-200 hover:bg-blue-700 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {loading ? (
                                <div className="flex items-center justify-center">
                                    <div
                                        className="mr-2 h-4 w-4 animate-spin rounded-full border-b-2 border-white"></div>
                                    Submitting...
                                </div>
                            ) : (
                                'Submit Requests'
                            )}
                        </button>

                        <button
                            type="button"
                            onClick={clearForm}
                            className="rounded-lg bg-gray-200 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500"
                        >
                            Clear
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

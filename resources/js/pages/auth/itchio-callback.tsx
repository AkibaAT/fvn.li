import ItchioIcon from '@/components/icons/itchio';
import {Head, Link} from '@inertiajs/react';
import {useEffect, useRef, useState} from 'react';

export default function ItchioCallback() {
    const [error, setError] = useState<string | null>(null);
    const cardRef = useRef<HTMLDivElement | null>(null);
    const title = 'Completing itch.io Login';

    useEffect(() => {
        if (typeof window === 'undefined') return;
        // Parse the hash fragment from the URL and forward to server callback
        const hash = window.location.hash?.substring(1) ?? '';
        const params = new URLSearchParams(hash);
        const accessToken = params.get('access_token');

        if (accessToken) {
            window.location.href = `${route('auth.itchio.process')}?hash=${encodeURIComponent(hash)}`;
        } else {
            setError(
                "We couldn't complete your itch.io login. Please try again.",
            );
        }
    }, []);

    return (
        <>
            <Head title={title}/>

            <div className="flex min-h-[60vh] items-center justify-center">
                <div
                    ref={cardRef}
                    className="w-full max-w-md rounded-lg bg-white p-8 text-center shadow-md dark:bg-gray-800"
                >
                    <h2 className="mb-6 text-xl font-bold text-gray-900 dark:text-gray-100">
                        {title}
                    </h2>

                    {!error ? (
                        <div className="flex flex-col items-center justify-center space-y-4">
                            <ItchioIcon className="h-12 w-12 text-itchio"/>
                            <div className="flex items-center justify-center space-x-3">
                                <svg
                                    className="h-5 w-5 animate-spin text-blue-500"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        className="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        strokeWidth="4"
                                    ></circle>
                                    <path
                                        className="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    ></path>
                                </svg>
                                <span className="text-gray-700 dark:text-gray-300">
                                    Processing authentication...
                                </span>
                            </div>
                        </div>
                    ) : (
                        <div>
                            <div className="flex items-center justify-center space-x-3 text-red-600 dark:text-red-400">
                                <svg
                                    className="h-5 w-5"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fillRule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clipRule="evenodd"
                                    />
                                </svg>
                                <span>{error}</span>
                            </div>
                            <div className="mt-6">
                                <Link
                                    href={route('home')}
                                    className="text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    Go back and try again
                                </Link>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

import {useState, useRef, useEffect} from 'react';

interface ReportReviewModalProps {
    ratingId: number;
    reviewerName: string;
    isOpen: boolean;
    onClose: () => void;
}

const REPORT_REASONS = [
    {value: 'hate_speech', label: 'Hate speech or discrimination'},
    {value: 'spam', label: 'Spam or advertising'},
    {value: 'harassment', label: 'Harassment or personal attacks'},
    {value: 'spoilers', label: 'Unmarked spoilers'},
    {value: 'off_topic', label: 'Off-topic or irrelevant'},
    {value: 'other', label: 'Other'},
];

export default function ReportReviewModal({ratingId, reviewerName, isOpen, onClose}: ReportReviewModalProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const [reason, setReason] = useState('');
    const [details, setDetails] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [message, setMessage] = useState<{type: 'success' | 'error'; text: string} | null>(null);

    useEffect(() => {
        if (isOpen) {
            dialogRef.current?.showModal();
        } else {
            dialogRef.current?.close();
        }
    }, [isOpen]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!reason) return;

        setIsSubmitting(true);
        try {
            const response = await window.axios.post(
                route('react-api.review-reports.store', {rating: ratingId}),
                {reason, details: details.trim() || null}
            );

            if (response.data.success) {
                setMessage({type: 'success', text: response.data.message});
                setTimeout(() => {
                    onClose();
                    setMessage(null);
                    setReason('');
                    setDetails('');
                }, 2000);
            }
        } catch (error: any) {
            const msg = error?.response?.data?.message || 'Failed to submit report';
            setMessage({type: 'error', text: msg});
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <dialog
            ref={dialogRef}
            className="m-auto w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800 dark:text-gray-100 backdrop:backdrop-blur-md"
            onClick={(e) => {
                const rect = e.currentTarget.getBoundingClientRect();
                if (
                    e.clientX < rect.left || e.clientX > rect.right ||
                    e.clientY < rect.top || e.clientY > rect.bottom
                ) {
                    onClose();
                }
            }}
        >
            <div onClick={(e) => e.stopPropagation()}>
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                        Report Review
                    </h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Report the review by <strong>{reviewerName}</strong> for violating community guidelines.
                </p>

                <form onSubmit={handleSubmit}>
                    <div className="mb-4">
                        <label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Reason *
                        </label>
                        <div className="space-y-2">
                            {REPORT_REASONS.map((r) => (
                                <label key={r.value} className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="radio"
                                        name="reason"
                                        value={r.value}
                                        checked={reason === r.value}
                                        onChange={(e) => setReason(e.target.value)}
                                        className="text-blue-600 focus:ring-blue-500 dark:border-gray-600"
                                    />
                                    <span className="text-sm text-gray-700 dark:text-gray-300">{r.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="mb-4">
                        <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Additional details (optional)
                        </label>
                        <textarea
                            value={details}
                            onChange={(e) => setDetails(e.target.value)}
                            placeholder="Provide any additional context..."
                            rows={3}
                            maxLength={1000}
                            className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                        />
                    </div>

                    {message && (
                        <div className={`mb-3 text-sm ${message.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                            {message.text}
                        </div>
                    )}

                    <div className="flex items-center gap-2">
                        <button
                            type="submit"
                            disabled={!reason || isSubmitting}
                            className="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {isSubmitting ? 'Submitting...' : 'Submit Report'}
                        </button>
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </dialog>
    );
}

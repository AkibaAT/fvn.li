import { notify } from '@/components/toast';
import { authenticatedFetch } from '@/utils/csrf';
import { useState } from 'react';
import {
    type OptimizedScreenshotVariants,
} from '@/constants/screenshot-variants';

interface Screenshot {
    url: string;
    thumbnail_url: string;
    optimized?: OptimizedScreenshotVariants;
}

interface InlineMediaEditorProps {
    gameSlug: string;
    thumbnail: string | null;
    screenshots: Screenshot[];
    canEdit: boolean;
    isAdmin?: boolean;
    onUpdate: (thumbnail: string | null, screenshots: Screenshot[]) => void;
}

export default function InlineMediaEditor({
    gameSlug,
    thumbnail,
    screenshots,
    canEdit,
    isAdmin = false,
    onUpdate,
}: InlineMediaEditorProps) {
    const [uploadingThumbnail, setUploadingThumbnail] = useState(false);
    const [uploadingScreenshots, setUploadingScreenshots] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const [isEditing, setIsEditing] = useState(false);

    const handleThumbnailUpload = async (file: File) => {
        if (!file.type.startsWith('image/')) {
            notify('Please upload an image file', 'error');
            return;
        }

        setUploadingThumbnail(true);
        const formData = new FormData();
        formData.append('thumbnail', file);

        try {
            const res = await authenticatedFetch(
                route('react-api.my-games.thumbnail.update', { game: gameSlug }),
                {
                    method: 'POST',
                    body: formData,
                },
            );
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data?.success === false) {
                notify(data?.message || 'Failed to upload thumbnail', 'error');
                return;
            }

            onUpdate(data.thumbnail_url, screenshots);
            notify('Thumbnail updated successfully', 'success');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Failed to upload thumbnail';
            notify(errorMessage, 'error');
        } finally {
            setUploadingThumbnail(false);
        }
    };

    const handleThumbnailDelete = async () => {
        try {
            const res = await authenticatedFetch(
                route('react-api.my-games.thumbnail.delete', { game: gameSlug }),
                {
                    method: 'DELETE',
                },
            );
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data?.success === false) {
                notify(data?.message || 'Failed to delete thumbnail', 'error');
                return;
            }

            onUpdate(null, screenshots);
            notify('Thumbnail deleted successfully', 'success');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Failed to delete thumbnail';
            notify(errorMessage, 'error');
        }
    };

    const handleScreenshotUpload = async (files: FileList) => {
        const imageFiles = Array.from(files).filter(file => file.type.startsWith('image/'));
        if (imageFiles.length === 0) {
            notify('Please upload image files', 'error');
            return;
        }

        setUploadingScreenshots(true);
        const formData = new FormData();
        imageFiles.forEach(file => {
            formData.append('screenshots[]', file);
        });

        try {
            const res = await authenticatedFetch(
                route('react-api.my-games.screenshots.upload', { game: gameSlug }),
                {
                    method: 'POST',
                    body: formData,
                },
            );
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data?.success === false) {
                notify(data?.message || 'Failed to upload screenshots', 'error');
                return;
            }

            onUpdate(thumbnail, data.screenshots || []);
            notify('Screenshots uploaded successfully', 'success');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Failed to upload screenshots';
            notify(errorMessage, 'error');
        } finally {
            setUploadingScreenshots(false);
        }
    };

    const handleScreenshotDelete = async (index: number) => {
        try {
            const res = await authenticatedFetch(
                route('react-api.my-games.screenshots.delete', { game: gameSlug, index }),
                {
                    method: 'DELETE',
                },
            );
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data?.success === false) {
                notify(data?.message || 'Failed to delete screenshot', 'error');
                return;
            }

            onUpdate(thumbnail, data.screenshots || []);
            notify('Screenshot deleted successfully', 'success');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Failed to delete screenshot';
            notify(errorMessage, 'error');
        }
    };

    
    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
    };

    const handleDrop = (e: React.DragEvent, type: 'thumbnail' | 'screenshots') => {
        e.preventDefault();
        setDragOver(false);

        const files = e.dataTransfer.files;
        if (type === 'thumbnail' && files.length === 1) {
            handleThumbnailUpload(files[0]);
        } else if (type === 'screenshots' && files.length > 0) {
            handleScreenshotUpload(files);
        }
    };

    if (!canEdit) {
        return null;
    }

    return (
        <div className="space-y-6">
            {/* Edit Toggle */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                        Media Management
                    </h3>
                    {isAdmin && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full dark:bg-blue-900 dark:text-blue-200">
                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Admin Access
                        </span>
                    )}
                </div>
                <button
                    onClick={() => setIsEditing(!isEditing)}
                    className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                        isEditing
                            ? 'bg-gray-600 text-white hover:bg-gray-700'
                            : 'bg-blue-600 text-white hover:bg-blue-700'
                    }`}
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d={isEditing ? "M6 18L18 6M6 6l12 12" : "M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"}
                        />
                    </svg>
                    {isEditing ? 'Cancel' : 'Edit Media'}
                </button>
            </div>

            {isEditing && (
                <>
                    {/* Thumbnail Management */}
                    <div className="border border-gray-200 rounded-lg p-4 dark:border-gray-700">
                        <h4 className="text-md font-medium text-gray-900 dark:text-white mb-4">
                            Thumbnail
                        </h4>
                        <div className="flex items-start gap-6">
                            <div
                                className={`relative border-2 border-dashed rounded-lg p-4 transition-colors ${
                                    dragOver
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                        : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'
                                }`}
                                onDragOver={handleDragOver}
                                onDragLeave={handleDragLeave}
                                onDrop={(e) => handleDrop(e, 'thumbnail')}
                            >
                                {thumbnail ? (
                                    <div className="relative">
                                        <img
                                            src={thumbnail}
                                            alt="Game thumbnail"
                                            className="w-32 h-32 object-cover rounded-lg"
                                        />
                                        <button
                                            onClick={handleThumbnailDelete}
                                            disabled={uploadingThumbnail}
                                            className="absolute -top-2 -right-2 bg-red-600 text-white rounded-full p-1 hover:bg-red-700 transition-colors"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                ) : (
                                    <div className="w-32 h-32 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                        <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                )}
                            </div>

                            <div className="flex-1">
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Upload Thumbnail
                                </label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => {
                                        if (e.target.files?.[0]) {
                                            handleThumbnailUpload(e.target.files[0]);
                                        }
                                    }}
                                    disabled={uploadingThumbnail}
                                    className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-600 file:text-white hover:file:bg-blue-700 disabled:opacity-50"
                                />
                                <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Recommended: 16:9 aspect ratio, max 5MB. Supports JPG, PNG, WebP.
                                </p>
                                {uploadingThumbnail && (
                                    <div className="mt-2 flex items-center text-sm text-blue-600 dark:text-blue-400">
                                        <svg className="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Uploading thumbnail...
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Screenshots Management */}
                    <div className="border border-gray-200 rounded-lg p-4 dark:border-gray-700">
                        <div className="flex items-center justify-between mb-4">
                            <h4 className="text-md font-medium text-gray-900 dark:text-white">
                                Screenshots
                            </h4>
                            <label className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors cursor-pointer disabled:opacity-50">
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Add Screenshots</span>
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    onChange={(e) => {
                                        if (e.target.files) {
                                            handleScreenshotUpload(e.target.files);
                                        }
                                    }}
                                    disabled={uploadingScreenshots}
                                    className="hidden"
                                />
                            </label>
                        </div>

                        <div
                            className={`border-2 border-dashed rounded-lg p-6 mb-4 transition-colors ${
                                dragOver
                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                    : 'border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500'
                            }`}
                            onDragOver={handleDragOver}
                            onDragLeave={handleDragLeave}
                            onDrop={(e) => handleDrop(e, 'screenshots')}
                        >
                            {screenshots.length > 0 ? (
                                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                    {screenshots.map((screenshot, index) => (
                                        <div key={index} className="relative group">
                                            <img
                                                src={screenshot.url}
                                                alt={`Screenshot ${index + 1}`}
                                                className="w-full h-32 object-cover rounded-lg"
                                            />
                                            <button
                                                onClick={() => handleScreenshotDelete(index)}
                                                className="absolute top-2 right-2 bg-red-600 text-white rounded-full p-2 hover:bg-red-700 transition-colors shadow-lg"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                            <div className="absolute bottom-2 left-2 bg-black bg-opacity-75 text-white text-xs px-2 py-1 rounded">
                                                #{index + 1}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center">
                                    <svg className="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p className="text-gray-500 dark:text-gray-400 mb-2">
                                        No screenshots uploaded yet
                                    </p>
                                    <p className="text-sm text-gray-400 dark:text-gray-500">
                                        Drag and drop images here or click "Add Screenshots" to upload
                                    </p>
                                </div>
                            )}
                        </div>

                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Recommended: 16:9 aspect ratio, max 10MB per image. Supports JPG, PNG, WebP. Multiple files can be uploaded at once.
                        </p>

                        {uploadingScreenshots && (
                            <div className="mt-4 flex items-center text-sm text-blue-600 dark:text-blue-400">
                                <svg className="w-4 h-4 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Uploading screenshots...
                            </div>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}
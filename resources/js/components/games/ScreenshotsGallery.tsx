import React from 'react';
import {
  SCREENSHOT_VARIANTS,
  type ScreenshotVariant,
  type OptimizedScreenshotVariants,
} from '@/constants/screenshot-variants';

export interface Screenshot {
  url: string;
  thumbnail_url: string;
  optimized?: OptimizedScreenshotVariants;
}

function getOptimizedScreenshotUrl(
  screenshot: Screenshot,
  variant: ScreenshotVariant = SCREENSHOT_VARIANTS.DEFAULT,
  fallbackToOriginal: boolean = true
): string {
  // Try optimized version first
  const path = screenshot.optimized?.[variant]?.path;
  if (path) {
    // SSR-safe: use relative path instead of window.location.origin
    return `/storage/${path}`;
  }

  // Fallback to original URLs if requested
  if (fallbackToOriginal) {
    if (variant === SCREENSHOT_VARIANTS.SMALL || variant === SCREENSHOT_VARIANTS.DEFAULT) {
      return screenshot.thumbnail_url || screenshot.url;
    }
    return screenshot.url;
  }

  // If no optimized version and no fallback, return empty string
  return '';
}

export interface ScreenshotsGalleryProps {
  screenshots: Screenshot[];
  blur?: boolean;
  onOpenLightbox?: (index: number) => void;
  canEdit?: boolean;
  gameSlug?: string;
  onUpdate?: (thumbnail: string | null, screenshots: Screenshot[]) => void;
}

export default function ScreenshotsGallery({ screenshots, blur = false, onOpenLightbox, canEdit = false, gameSlug, onUpdate }: ScreenshotsGalleryProps) {
  // Only hide the entire section if there are no screenshots AND user cannot edit
  if ((!screenshots || screenshots.length === 0) && !canEdit) return null;

  // Don't blur screenshots if user can edit
  const shouldBlur = blur && !canEdit;

  const handleScreenshotUpload = async (files: FileList) => {
    const imageFiles = Array.from(files).filter(file => file.type.startsWith('image/'));
    if (imageFiles.length === 0) {
      alert('Please upload image files');
      return;
    }

    const formData = new FormData();
    imageFiles.forEach(file => {
      formData.append('screenshots[]', file);
    });

    try {
      const res = await (window as any).axios.post(
        (window as any).route('react-api.my-games.screenshots.upload', { game: gameSlug }),
        formData
      );
      if (res.data?.success) {
        onUpdate?.(null, res.data.screenshots || []);
      }
    } catch (e) {
      console.error('Failed to upload screenshots', e);
    }
  };

  const handleScreenshotDelete = async (index: number) => {
    if (!confirm('Delete this screenshot?')) return;
    try {
      const res = await (window as any).axios.delete(
        (window as any).route('react-api.my-games.screenshots.delete', { game: gameSlug }),
        { data: { index } }
      );
      if (res.data?.success) {
        onUpdate?.(null, res.data.screenshots || []);
      }
    } catch (e) {
      console.error('Failed to delete screenshot', e);
    }
  };

  return (
    <div id="screenshots" className="mb-6 scroll-mt-28 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Screenshots</h2>
        {canEdit && (
          <label className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors cursor-pointer">
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
              className="hidden"
            />
          </label>
        )}
      </div>

      {shouldBlur && (
        <div className="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/30">
          <div className="flex items-start">
            <div className="flex-shrink-0">
              <svg className="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.592c.75 1.335-.213 2.993-1.742 2.993H3.48c-1.53 0-2.492-1.658-1.743-2.993L8.257 3.1zM11 13a1 1 0 10-2 0 1 1 0 002 0zm-1-8a1 1 0 00-.894.553l-3 6A1 1 0 006 12h8a1 1 0 00.894-1.447l-3-6A1 1 0 0010 5z" clipRule="evenodd" />
              </svg>
            </div>
            <div className="ml-3">
              <h3 className="text-sm font-medium text-yellow-800 dark:text-yellow-200">Content Warning</h3>
              <div className="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                <p>Screenshots are blurred as they may contain sensitive or NSFW content. Click on any screenshot to view it in full.</p>
              </div>
            </div>
          </div>
        </div>
      )}

      {screenshots && screenshots.length > 0 ? (
        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4" id="screenshots-gallery">
          {screenshots.map((screenshot, index) => {
          const thumbnailUrl = getOptimizedScreenshotUrl(screenshot, SCREENSHOT_VARIANTS.DEFAULT, true);
          const fullUrl = getOptimizedScreenshotUrl(screenshot, SCREENSHOT_VARIANTS.LARGE, true);

          return (
            <div
              key={`${screenshot.url}-${index}`}
              className="group relative h-32 w-full"
            >
              <a
                href={fullUrl}
                className="block h-full overflow-hidden rounded-lg border border-gray-200 bg-gray-50 shadow-sm transition-all hover:shadow-md dark:border-gray-700 dark:bg-gray-900"
                onClick={(e) => {
                  e.preventDefault();
                  onOpenLightbox?.(index);
                }}
              >
                <div className="absolute inset-0">
                  <img
                    src={thumbnailUrl}
                    alt={`Screenshot ${index + 1}`}
                    className={`h-full w-full object-cover ${shouldBlur ? 'blur-sm transition-all duration-300 hover:blur-none' : ''}`}
                  />
                </div>
                <div className="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
              </a>
              {canEdit && (
                <button
                  onClick={() => handleScreenshotDelete(index)}
                  className="absolute top-2 right-2 bg-red-600 text-white rounded-full p-2 hover:bg-red-700 transition-colors shadow-lg z-10"
                >
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              )}
            </div>
          );
        })}
        </div>
      ) : (
        <div className="text-center py-12 text-gray-500 dark:text-gray-400">
          <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <p className="mt-2">No screenshots yet. Click "Add Screenshots" to upload some.</p>
        </div>
      )}
    </div>
  );
}


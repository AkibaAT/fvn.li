import React, {useCallback, useEffect, useMemo, useState} from 'react';
import {
  SCREENSHOT_VARIANTS,
  type ScreenshotVariant,
  type OptimizedScreenshotVariants,
} from '@/constants/screenshot-variants';
import type { Screenshot as GalleryScreenshot } from '@/components/games/ScreenshotsGallery';

export type Screenshot = GalleryScreenshot;

function getOptimizedScreenshotUrl(
  screenshot: Screenshot,
  variant: ScreenshotVariant = SCREENSHOT_VARIANTS.DEFAULT,
  fallbackToOriginal: boolean = true
): string {
  const path = screenshot.optimized?.[variant]?.path;
  // SSR-safe: use relative path instead of window.location.origin
  if (path) return `/storage/${path}`;
  if (!fallbackToOriginal) return '';
  if (variant === SCREENSHOT_VARIANTS.SMALL || variant === SCREENSHOT_VARIANTS.DEFAULT) {
    return screenshot.thumbnail_url || screenshot.url;
  }
  return screenshot.url;
}

export interface ScreenshotsLightboxProps {
  isOpen: boolean;
  screenshots: Screenshot[];
  startIndex?: number;
  onClose: () => void;
}

export default function ScreenshotsLightbox({ isOpen, screenshots, startIndex = 0, onClose }: ScreenshotsLightboxProps) {
  const [index, setIndex] = useState<number>(startIndex);
  const [isZoomed, setIsZoomed] = useState<boolean>(false);
  const [touchStart, setTouchStart] = useState<number | null>(null);
  const [touchEnd, setTouchEnd] = useState<number | null>(null);

  useEffect(() => {
    if (isOpen) {
      setIndex(startIndex);
      setIsZoomed(false);
    }
  }, [isOpen, startIndex]);

  const currentImage = useMemo(() => {
    if (!isOpen || !screenshots || screenshots.length === 0) return '';
    return getOptimizedScreenshotUrl(screenshots[index], SCREENSHOT_VARIANTS.LARGE, true);
  }, [isOpen, screenshots, index]);

  const navigate = useCallback((direction: 'prev' | 'next') => {
    if (!screenshots || screenshots.length === 0) return;
    setIndex((prev) => {
      if (direction === 'prev') return prev > 0 ? prev - 1 : screenshots.length - 1;
      return prev < screenshots.length - 1 ? prev + 1 : 0;
    });
  }, [screenshots]);

  useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (e: KeyboardEvent) => {
      switch (e.key) {
        case 'Escape':
          onClose();
          break;
        case 'ArrowLeft':
          navigate('prev');
          break;
        case 'ArrowRight':
          navigate('next');
          break;
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, navigate, onClose]);

  const handleTouchStart = (e: React.TouchEvent) => {
    setTouchEnd(null);
    setTouchStart(e.targetTouches[0].clientX);
  };
  const handleTouchMove = (e: React.TouchEvent) => {
    setTouchEnd(e.targetTouches[0].clientX);
  };
  const handleTouchEnd = () => {
    if (!touchStart || !touchEnd) return;
    const distance = touchStart - touchEnd;
    const isLeftSwipe = distance > 50;
    const isRightSwipe = distance < -50;
    if (isLeftSwipe) navigate('next');
    else if (isRightSwipe) navigate('prev');
  };

  if (!isOpen) return null;
  if (!screenshots || screenshots.length === 0) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex flex-col bg-black bg-opacity-95"
      onClick={onClose}
    >
      {/* Top bar with close and counter */}
      <div className="flex items-center justify-between p-2 flex-shrink-0">
        <div className="text-white text-sm">
          {index + 1} / {screenshots.length}
        </div>
        <button
          onClick={(e) => { e.stopPropagation(); onClose(); }}
          className="cursor-pointer rounded-full bg-white bg-opacity-90 p-2 text-black transition-colors hover:bg-opacity-100"
          aria-label="Close lightbox"
        >
          <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>

      <div className="relative flex-1 min-h-0">
        {screenshots.length > 1 && (
          <button
            onClick={(e) => { e.stopPropagation(); navigate('prev'); }}
            className="cursor-pointer absolute left-4 z-20 rounded-full bg-white bg-opacity-90 p-3 text-black transition-colors hover:bg-opacity-100"
            style={{ top: '50%', transform: 'translateY(-50%)' }}
            aria-label="Previous screenshot"
          >
            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
          </button>
        )}

        <div className="h-full flex items-center justify-center pb-20">
          <img
            src={currentImage}
            alt={`Screenshot ${index + 1} of ${screenshots.length}`}
            className={`max-h-full max-w-full object-contain transition-transform duration-300 ${isZoomed ? 'scale-150 cursor-zoom-out' : 'cursor-zoom-in'}`}
            onClick={(e) => { e.stopPropagation(); setIsZoomed(z => !z); }}
            onTouchStart={handleTouchStart}
            onTouchMove={handleTouchMove}
            onTouchEnd={handleTouchEnd}
          />
        </div>

        {screenshots.length > 1 && (
          <button
            onClick={(e) => { e.stopPropagation(); navigate('next'); }}
            className="cursor-pointer absolute right-4 z-20 rounded-full bg-white bg-opacity-90 p-3 text-black transition-colors hover:bg-opacity-100"
            style={{ top: '50%', transform: 'translateY(-50%)' }}
            aria-label="Next screenshot"
          >
            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </button>
        )}
      </div>

      <div className="flex items-center justify-between px-4 py-2 bg-black bg-opacity-80">
        {screenshots.length > 1 ? (
          <div className="flex-1 flex justify-center mr-4 overflow-hidden">
            <div
              className="flex gap-2 transition-transform duration-300 ease-out"
              style={{ transform: `translateX(${-index * 72 + (Math.min(5, screenshots.length) - 1) * 36}px)` }}
            >
              {screenshots.map((screenshot, i) => {
                const thumbUrl = getOptimizedScreenshotUrl(screenshot, SCREENSHOT_VARIANTS.SMALL, true);
                return (
                  <button
                    key={i}
                    onClick={(e) => { e.stopPropagation(); setIndex(i); }}
                    className={`cursor-pointer flex-shrink-0 overflow-hidden rounded transition-all duration-300 ${i === index ? 'border-4 border-white shadow-lg scale-110 opacity-100' : 'border-2 border-transparent opacity-60 hover:opacity-100 hover:scale-105'}`}
                    aria-label={`Go to screenshot ${i + 1}`}
                  >
                    <img src={thumbUrl} alt={`Thumbnail ${i + 1}`} className="h-12 w-16 object-cover" />
                  </button>
                );
              })}
            </div>
          </div>
        ) : (
          <div />
        )}

        <div className="flex items-center gap-3">
          <a
            href={screenshots[index]?.url}
            target="_blank"
            rel="noopener noreferrer"
            className="rounded bg-white px-3 py-1 text-sm font-medium text-black hover:bg-gray-100"
            onClick={(e) => e.stopPropagation()}
          >
            Open original
          </a>
        </div>
      </div>
    </div>
  );
}


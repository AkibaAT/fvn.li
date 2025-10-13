import React from 'react';

interface TooltipProps {
    content: React.ReactNode;
    children: React.ReactNode;
    position?: 'top' | 'bottom' | 'left' | 'right';
    className?: string;
}

export default function Tooltip({
    content,
    children,
    position = 'top',
    className = '',
}: TooltipProps) {
    const [isVisible, setIsVisible] = React.useState(false);
    const tooltipRef = React.useRef<HTMLDivElement>(null);

    const positionClasses = {
        top: 'bottom-full left-1/2 mb-2 -translate-x-1/2',
        bottom: 'top-full left-1/2 mt-2 -translate-x-1/2',
        left: 'right-full top-1/2 mr-2 -translate-y-1/2',
        right: 'left-full top-1/2 ml-2 -translate-y-1/2',
    };

    const arrowClasses = {
        top: 'top-full left-1/2 -translate-x-1/2 border-l-4 border-r-4 border-t-4 border-l-transparent border-r-transparent',
        bottom: 'bottom-full left-1/2 -translate-x-1/2 border-l-4 border-r-4 border-b-4 border-l-transparent border-r-transparent',
        left: 'left-full top-1/2 -translate-y-1/2 border-t-4 border-b-4 border-l-4 border-t-transparent border-b-transparent',
        right: 'right-full top-1/2 -translate-y-1/2 border-t-4 border-b-4 border-r-4 border-t-transparent border-b-transparent',
    };

    return (
        <div className="relative inline-block">
            <div
                onMouseEnter={() => setIsVisible(true)}
                onMouseLeave={() => setIsVisible(false)}
                onFocus={() => setIsVisible(true)}
                onBlur={() => setIsVisible(false)}
            >
                {children}
            </div>
            
            {isVisible && (
                <div
                    ref={tooltipRef}
                    className={`absolute z-50 w-max rounded-md bg-gray-900 px-3 py-2 text-sm text-white shadow-lg dark:bg-gray-700 ${positionClasses[position]} ${className}`}
                    role="tooltip"
                >
                    {content}
                    <div
                        className={`absolute h-0 w-0 border-gray-900 dark:border-gray-700 ${arrowClasses[position]}`}
                    />
                </div>
            )}
        </div>
    );
}
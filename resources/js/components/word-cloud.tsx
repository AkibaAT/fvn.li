import type {FC} from 'react';
import {useMemo} from 'react';

type WordFrequencyData = {
    text: string;
    value: number;
};

type WordCloudProps = {
    data: WordFrequencyData[];
    width?: number;
    height?: number;
    onWordClick?: (word: string) => void;
};

export const WordCloud: FC<WordCloudProps> = ({
    data,
    width = 900,
    height = 450,
    onWordClick,
}) => {
    // Sort data by value and take top items
    const sortedData = useMemo(() => {
        return [...data].sort((a, b) => b.value - a.value);
    }, [data]);

    // Calculate font sizes based on frequency
    const maxValue = sortedData[0]?.value || 1;
    const minValue = sortedData[sortedData.length - 1]?.value || 1;

    const getFontSize = (value: number) => {
        const minSize = 12;
        const maxSize = 48;
        if (maxValue === minValue) return maxSize;
        return minSize + ((value - minValue) / (maxValue - minValue)) * (maxSize - minSize);
    };

    const getColor = (index: number) => {
        const colors = [
            '#3b82f6', // blue
            '#ef4444', // red
            '#10b981', // green
            '#f59e0b', // amber
            '#8b5cf6', // purple
            '#ec4899', // pink
            '#06b6d4', // cyan
            '#f97316', // orange
            '#84cc16', // lime
            '#6366f1', // indigo
        ];
        return colors[index % colors.length];
    };

    if (sortedData.length === 0) {
        return (
            <div
                className="flex items-center justify-center rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-700/30"
                style={{width, height}}
            >
                <p className="text-gray-500 dark:text-gray-400">
                    No word frequency data available
                </p>
            </div>
        );
    }

    return (
        <div
            className="relative overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
            style={{width, height}}
        >
            <div
                className="flex flex-wrap items-center justify-center gap-3 p-6"
                style={{width: '100%', height: '100%'}}
            >
                {sortedData.map((item, index) => {
                    const fontSize = getFontSize(item.value);
                    return (
                        <span
                            key={item.text}
                            className="inline-block cursor-pointer transition-transform hover:scale-110"
                            style={{
                                fontSize: `${fontSize}px`,
                                color: getColor(index),
                                fontWeight: fontSize > 30 ? 'bold' : 'normal',
                                lineHeight: 1.2,
                            }}
                            title={`${item.text}: ${item.value} occurrences - Click to search`}
                            onClick={() => onWordClick?.(item.text)}
                        >
                            {item.text}
                        </span>
                    );
                })}
            </div>
        </div>
    );
};

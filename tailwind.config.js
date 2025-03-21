/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
    ],
    safelist: [
        // Border colors for list cards
        'border-blue-500',
        'border-green-500',
        'border-yellow-500',
        'border-orange-500',
        'border-red-500',
        'border-gray-500',
        // Background colors for tags
        'bg-blue-100',
        'bg-green-100',
        'bg-yellow-100',
        'bg-orange-100',
        'bg-red-100',
        'bg-gray-100',
        // Background colors for dots
        'bg-blue-500',
        'bg-green-500',
        'bg-yellow-500',
        'bg-orange-500',
        'bg-red-500',
        'bg-gray-500',
        // Text colors for tags
        'text-blue-800',
        'text-green-800',
        'text-yellow-800',
        'text-orange-800',
        'text-red-800',
        'text-gray-800',
        // Dark mode background colors
        'dark:bg-blue-900',
        'dark:bg-green-900',
        'dark:bg-yellow-900',
        'dark:bg-orange-900',
        'dark:bg-red-900',
        'dark:bg-gray-900',
        // Dark mode text colors
        'dark:text-blue-200',
        'dark:text-green-200',
        'dark:text-yellow-200',
        'dark:text-orange-200',
        'dark:text-red-200',
        'dark:text-gray-200',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
}

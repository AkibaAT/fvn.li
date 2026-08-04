/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.ts",
        "./resources/**/*.svelte",
    ],
    theme: {
        extend: {
            screens: {
                'xs': '480px',
            },
        },
        container: {
            center: true,
            padding: {
                DEFAULT: '1rem',
                sm: '2rem',
                xs: '0.5rem',
            },
        },
    },
    plugins: [],
}

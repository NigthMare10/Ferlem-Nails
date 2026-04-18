import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            colors: {
                brand: {
                    cream: '#f7f2ec',
                    copper: '#8a5f4d',
                    gold: '#c8a670',
                    rose: '#a96e73',
                    ink: '#201816',
                },
            },
            fontFamily: {
                sans: ['Manrope', ...defaultTheme.fontFamily.sans],
                display: ['Cormorant Garamond', ...defaultTheme.fontFamily.serif],
            },
        },
    },

    plugins: [forms],
};

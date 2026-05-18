import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                'xs': ['14px', { lineHeight: '1.4' }],   // sous-titres sidebar (défaut Tailwind : 12px)
                'sm': ['16px', { lineHeight: '1.5' }],   // titres sidebar     (défaut Tailwind : 14px)
            },
            colors: {
                brand: {
                    dark:   '#0c0c1d',   // auth panel, login bg
                    darker: '#0f0f23',   // dashboard hero
                },
            },
            keyframes: {
                fadeInUp: {
                    '0%':   { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                fadeInDown: {
                    '0%':   { opacity: '0', transform: 'translateY(-8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                slideInRight: {
                    '0%':   { opacity: '0', transform: 'translateX(20px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
                scaleIn: {
                    '0%':   { opacity: '0', transform: 'scale(0.95)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
                shimmer: {
                    '0%':   { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
                progressBar: {
                    '0%':   { width: '100%' },
                    '100%': { width: '0%' },
                },
            },
            animation: {
                'fade-in-up':    'fadeInUp 0.35s ease-out forwards',
                'fade-in-down':  'fadeInDown 0.25s ease-out forwards',
                'slide-in-right':'slideInRight 0.3s ease-out forwards',
                'scale-in':      'scaleIn 0.2s ease-out forwards',
                'progress-bar':  'progressBar linear forwards',
            },
        },
    },

    plugins: [forms],
};

import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],
    safelist: [
        'bg-red-100',
        'text-red-800',
        'bg-amber-100',
        'text-amber-800',
        'bg-yellow-100',
        'text-yellow-800',
        'bg-green-100',
        'text-green-800',
        'bg-gray-100',
        'text-gray-800',
        'bg-slate-100',
        'text-slate-700',
        'bg-blue-100',
        'text-blue-800',
        'bg-purple-100',
        'text-purple-800',
        'bg-[#00494b]',
        'bg-amber-400',
        'bg-sky-500',
        'bg-emerald-500',
        'bg-slate-500',
        'bg-slate-400',
        'text-white',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'ione-blue': {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
                ink: '#0f172a',
                mist: '#f8fafc',
                spark: '#06b6d4',
            },
            boxShadow: {
                glow: '0 20px 45px -28px rgba(14, 116, 144, 0.45)',
            },
            keyframes: {
                'fade-in-up': {
                    '0%': { opacity: '0', transform: 'translateY(10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-6px)' },
                },
            },
            animation: {
                'fade-in-up': 'fade-in-up 0.5s ease-out both',
                float: 'float 5s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};

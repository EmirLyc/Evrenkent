import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // RichText okuma sayfası HTML'ini PHP'de üretiyor (gömülü belge işareti vb.) —
        // taranmazsa oradaki sınıfların @layer components kuralları CSS'ten atılıyor.
        './app/Support/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                serif: ['Fraunces', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                paper: '#FBF9F4',
                // Mockup'lardaki turuncu vurgu rengi — CTA butonları, aktif durumlar, rozetler.
                brand: {
                    50: '#FDF3E7',
                    100: '#FBE7CF',
                    200: '#F6CB96',
                    300: '#F0AF5E',
                    400: '#EB9743',
                    500: '#E2790E',
                    600: '#C86A0C',
                    700: '#9E540A',
                },
                // Süper Admin panelinin koyu lacivert rengi — mockup'tan
                // (dosyalar/2.4-)...png) piksel örneklenerek alındı (#03192F),
                // Tailwind'in slate-950'i (neredeyse siyah) mockup'a göre fazla
                // koyu/donuktu.
                navy: '#03192F',
            },
        },
    },

    plugins: [forms],
};

import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {
    defineConfig
} from 'vite';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    server: {
        host: '0.0.0.0', // Agar bisa diakses dari jaringan luar
        port: 5173,
        // Jika error pada HMR (React Refresh), tambahkan ini:
        hmr: {
            host: '192.168.1.203', // Sesuaikan dengan IP komputer server Anda
        },
    },
});
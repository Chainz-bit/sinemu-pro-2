import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

import { cloudflare } from "@cloudflare/vite-plugin";

export default defineConfig({
    plugins: [laravel({
        input: [
            'resources/js/entries/main.js',
            'resources/js/entries/manager.js',
            'resources/js/entries/super.js',
            'resources/js/entries/user.js',
            'resources/js/entries/auth-base.js',
            'resources/js/entries/auth-login.js',
            'resources/js/entries/auth-register.js',
            'resources/js/entries/manager-auth-login.js',
            'resources/js/entries/manager-auth-register.js',
            'resources/js/entries/super-auth-login.js',
            'resources/js/entries/app-layout.js',
        ],
        refresh: true,
    }), cloudflare()],
});
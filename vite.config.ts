import inertia from "@inertiajs/vite";
import { lattice } from "@lattice-php/lattice/vite";
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import laravel from "laravel-vite-plugin";
import path from "node:path";
import { defineConfig } from "vite";

export default defineConfig({
    resolve: {
        alias: {
            "@": path.resolve(import.meta.dirname, "resources/js"),
        },
    },
    plugins: [
        lattice({ icons: { dirs: ["resources/icons"] } }),
        laravel({
            input: ["resources/css/app.css", "resources/js/app.tsx"],
            refresh: true,
        }),
        inertia(),
        react(),
        tailwindcss(),
    ],
});

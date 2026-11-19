/// <reference types="vitest/config" />
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

// El frontend se compila dentro del Laravel que lo sirve: los archivos van a backend/public/app y Laravel los
// enlaza desde la página (resources/views/spa.blade.php). En desarrollo, "npm run dev" deja el archivo "hot"
// para que Laravel cargue los módulos desde este servidor, con recarga al guardar.
export default defineConfig({
  plugins: [
    // En las pruebas no hay Laravel que avisar: sin esto, vitest dejaría el archivo "hot" en backend/public.
    ...(process.env.VITEST
      ? []
      : [
          laravel({
            input: ['src/main.tsx'],
            publicDirectory: '../backend/public',
            buildDirectory: 'app',
            hotFile: '../backend/public/hot',
          }),
        ]),
    react(),
  ],
  // Las pruebas comparan las respuestas de mentira con el contrato de la API, que vive fuera de esta carpeta.
  server: process.env.VITEST
    ? {
        fs: {
          allow: [fileURLToPath(new URL('.', import.meta.url)), fileURLToPath(new URL('../docs/api', import.meta.url))],
        },
      }
    : undefined,
  // La carpeta de salida queda fuera del proyecto: hay que decir que sí se vacía antes de compilar.
  build: { emptyOutDir: true },
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['src/test/setup.ts'],
    css: false,
    restoreMocks: true,
  },
});

import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

export default defineConfig({
  plugins: [react()],
  base: '/react-dist/',
  server: {
    // Classic PHP site (127.0.0.1:8080) loads Vite client (127.0.0.1:5173)
    // -> needs CORS headers for @vite/client and module requests.
    cors: true,
  },
  build: {
    manifest: true,
    outDir: path.resolve(__dirname, '..', 'react-dist'),
    emptyOutDir: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'src', 'main.tsx'),
    },
  },
});


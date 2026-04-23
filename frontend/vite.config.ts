import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

export default defineConfig({
  plugins: [react()],
  base: '/react-dist/',
  build: {
    manifest: true,
    outDir: path.resolve(__dirname, '..', 'react-dist'),
    emptyOutDir: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'src', 'main.tsx'),
    },
  },
});


/// <reference types="vitest" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    include: ['src/**/*.{test,spec}.{ts,tsx}'],
    coverage: {
      reporter: ['text', 'html'],
      include: ['src/ui/**', 'src/hooks/**', 'src/lib/**'],
    },
  },
  server: {
    port: 5175,
    proxy: {
      '/api': {
        target: 'http://localhost:8002',
        changeOrigin: true,
        withCredentials: true,
      },
      '/sanctum': {
        target: 'http://localhost:8002',
        changeOrigin: true,
        withCredentials: true,
      },
    },
  },
  build: {
    // Raise chunk-size warning threshold now that heavy deps are split out
    chunkSizeWarningLimit: 600,
    rollupOptions: {
      output: {
        manualChunks: (id: string) => {
          if (!id.includes('node_modules')) return undefined
          // Split large vendor libs into their own chunks so route chunks stay small
          if (id.includes('recharts') || id.includes('d3-')) return 'vendor-charts'
          if (id.includes('@tanstack/react-query')) return 'vendor-query'
          if (id.includes('react-router')) return 'vendor-router'
          if (id.includes('react-dom') || id.includes('scheduler') || /\/react\//.test(id)) return 'vendor-react'
          if (id.includes('axios')) return 'vendor-http'
          return 'vendor'
        },
      },
    },
  },
})

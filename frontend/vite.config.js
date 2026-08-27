import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// The API and the uploaded certificates are proxied through the dev server, so
// the browser only ever talks to one origin. That keeps CORS out of the way and
// makes the app work identically on http://localhost:5173 and
// http://127.0.0.1:5173.
const backend = process.env.VITE_BACKEND_ORIGIN || 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': { target: backend, changeOrigin: true },
      '/storage': { target: backend, changeOrigin: true },
    },
  },
})

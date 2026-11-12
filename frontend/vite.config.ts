import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// The dev server runs inside the ddev web container and forwards /api to Symfony (nginx on port 80),
// so frontend and API share one origin locally just like in production.
export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    allowedHosts: ['.ddev.site'],
    proxy: {
      '/api': { target: 'http://localhost', changeOrigin: false, xfwd: true },
    },
  },
})

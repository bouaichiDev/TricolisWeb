/// <reference types="vitest/config" />
import { availableParallelism } from 'node:os'
import path from 'node:path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

/**
 * Portail client : application distincte du back-office (`../frontend`),
 * servie sur son propre port, qui parle au même backend Laravel sous
 * `/api/v1/customer`.
 *
 * Un build de production exige `VITE_API_URL` (Phase 8) : sans elle, le
 * bundle viserait silencieusement l'API locale.
 */
export default defineConfig(({ command, mode }) => {
  if (command === 'build' && mode === 'production' && !loadEnv(mode, process.cwd(), 'VITE_').VITE_API_URL) {
    throw new Error('VITE_API_URL est obligatoire pour un build de production du portail client.')
  }

  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        '@': path.resolve(import.meta.dirname, './src'),
      },
    },
    server: {
      port: 5180,
    },
    test: {
      globals: true,
      environment: 'jsdom',
      setupFiles: ['./src/test/setup.ts'],
      css: false,
      exclude: ['node_modules/**', 'dist/**', 'e2e/**'],
      testTimeout: 20_000,
      maxWorkers: Math.min(8, availableParallelism()),
    },
  }
})

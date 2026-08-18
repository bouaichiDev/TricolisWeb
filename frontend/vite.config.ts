/// <reference types="vitest/config" />
import path from 'node:path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, './src'),
    },
  },
  server: {
    port: 5173,
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    /**
     * 20 secondes au lieu de 5.
     *
     * Les tests pilotent l'interface par `userEvent`, qui simule la frappe
     * caractere par caractere avec les delais reels. Passe une vingtaine de
     * fichiers executes en parallele, la contention machine fait depasser les
     * 5 secondes par defaut a des tests qui passent en 3 secondes isoles.
     *
     * Le delai ne ralentit rien quand tout va bien : il ne s'applique qu'a
     * l'attente maximale, pas a la duree effective.
     */
    testTimeout: 20_000,
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      exclude: ['src/test/**', 'src/**/*.d.ts', 'src/main.tsx'],
    },
  },
})

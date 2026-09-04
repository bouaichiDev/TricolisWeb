/// <reference types="vitest/config" />
import { availableParallelism } from 'node:os'
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
     * caractere par caractere. Le delai ne ralentit rien quand tout va bien :
     * il ne s'applique qu'a l'attente maximale, pas a la duree effective.
     */
    testTimeout: 20_000,

    /**
     * Huit processus au plus, quel que soit le nombre de coeurs.
     *
     * Sans plafond, Vitest en lance un par coeur. Sur vingt coeurs, vingt
     * environnements jsdom se disputent la memoire et le temps CPU : la suite
     * passait de 116 a **832 secondes** de temps processeur, et deux tests du
     * parcours de commande depassaient les 20 secondes alors qu'ils tiennent en
     * trois isoles. Ce n'etait pas une lenteur de leur code, mais l'etranglement
     * de tous par tous.
     *
     * Mesures sur cette machine : 20 workers -> 91 s et deux echecs ;
     * 8 -> 34 s ; 6 -> 42 s ; 4 -> 56 s. Au-dela de huit, le parallelisme coute
     * plus qu'il ne rapporte.
     */
    maxWorkers: Math.min(8, availableParallelism()),
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      exclude: ['src/test/**', 'src/**/*.d.ts', 'src/main.tsx'],
    },
  },
})

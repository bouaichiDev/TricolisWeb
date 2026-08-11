import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach, vi } from 'vitest'

afterEach(() => {
  cleanup()
})

/**
 * `matchMedia` n'existe pas sous jsdom, et le layout l'interroge pour decider
 * s'il affiche la barre laterale ou le tiroir mobile. Sans ce doublon, tout
 * test montant le layout echouerait avant d'avoir rien verifie.
 */
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: (query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  }),
})

/** Sonner et Radix s'appuient dessus ; jsdom ne le fournit pas. */
globalThis.ResizeObserver = class {
  observe() {}
  unobserve() {}
  disconnect() {}
}

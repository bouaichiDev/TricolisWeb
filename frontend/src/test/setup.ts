import '@testing-library/jest-dom/vitest'
import { cleanup, configure } from '@testing-library/react'
import { toast } from 'sonner'
import { afterAll, afterEach, beforeAll, vi } from 'vitest'

import { server } from './server'
// Les tests interrogent l'interface par ses libellés visibles ; sans cette
// initialisation, les composants afficheraient les clés i18n brutes.
import '@/app/i18n'

/**
 * Delai des requetes asynchrones de Testing Library.
 *
 * `findBy*` et `waitFor` ont leur propre limite — 1 seconde par defaut — qui
 * ne depend pas de `testTimeout`. Passe une vingtaine de fichiers executes en
 * parallele, la contention machine la fait depasser par des assertions qui
 * aboutissent en quelques centaines de millisecondes isolees.
 *
 * Le delai ne ralentit rien : il borne l'attente maximale, pas la duree reelle.
 */
configure({ asyncUtilTimeout: 5_000 })

beforeAll(() => {
  server.listen({ onUnhandledRequest: 'error' })
})

afterEach(() => {
  cleanup()
  server.resetHandlers()
  // `sonner` garde ses notifications dans un magasin de module, que `cleanup`
  // ne touche pas : sans cela, un toast d'un test survit au suivant et un
  // « je ne notifie rien » passe au rouge a cause du voisin.
  toast.dismiss()
})

afterAll(() => {
  server.close()
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

/**
 * Capture du pointeur et defilement programmatique.
 *
 * Radix s'en sert pour ses listes deroulantes ; jsdom ne les implemente pas,
 * et leur absence fait echouer l'ouverture d'un `Select` avant toute
 * assertion. Ce sont des manques de l'environnement, pas du composant.
 */
Element.prototype.hasPointerCapture = () => false
Element.prototype.setPointerCapture = () => {}
Element.prototype.releasePointerCapture = () => {}
Element.prototype.scrollIntoView = () => {}

/**
 * URL d'objet.
 *
 * jsdom ne l'implemente pas, et les apercus de documents s'en servent pour
 * republier au navigateur un fichier recupere avec ses en-tetes
 * d'authentification. Sans ce doublon, toute vignette ferait echouer le test
 * sur un manque de l'environnement, pas du composant.
 */
URL.createObjectURL = () => 'blob:document'
URL.revokeObjectURL = () => {}

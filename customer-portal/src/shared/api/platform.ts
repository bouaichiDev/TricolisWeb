import { API_ROOT } from './apiBase'

/**
 * Les deux routes publiques de l'installation : sa configuration et son logo.
 *
 * Elles vivent hors de `/api/v1/customer` — le back-office les lit aussi, dès
 * son écran de connexion. Le portail affiche ainsi la même marque, sans jeton.
 */
const ROOT = API_ROOT

export interface PlatformConfiguration {
  hasDefaultLogo: boolean
}

export const platformApi = {
  async configuration(): Promise<PlatformConfiguration> {
    const response = await fetch(`${ROOT}/configuration`, { headers: { Accept: 'application/json' } })

    if (!response.ok) throw new Error('Configuration indisponible.')

    return ((await response.json()) as { data: PlatformConfiguration }).data
  },

  async logo(): Promise<Blob> {
    const response = await fetch(`${ROOT}/configuration/logo`)

    if (!response.ok) throw new Error('Logo indisponible.')

    return response.blob()
  },
}

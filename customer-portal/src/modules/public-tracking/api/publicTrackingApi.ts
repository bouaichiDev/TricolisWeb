import { NetworkError, toApiError } from '@/shared/api/errors'

import type { PublicTracking } from '../types'
import { API_ROOT } from '@/shared/api/apiBase'

/**
 * L'API publique du lien de suivi. Hors du client du portail : aucune session,
 * aucun en-tête d'autorisation — le jeton du lien est le seul secret, et un
 * jeton de portail n'a pas à accompagner cette requête.
 */
const ROOT = `${API_ROOT}/public/tracking`

export const publicTrackingApi = {
  async get(token: string): Promise<PublicTracking> {
    let response: Response

    try {
      response = await fetch(`${ROOT}/${encodeURIComponent(token)}`, { headers: { Accept: 'application/json' }, referrerPolicy: 'no-referrer' })
    } catch {
      throw new NetworkError()
    }

    if (!response.ok) throw await toApiError(response)

    return ((await response.json()) as { data: PublicTracking }).data
  },

  logoUrl: (token: string) => `${ROOT}/${encodeURIComponent(token)}/logo`,
}

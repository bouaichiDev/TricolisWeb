import { api } from '@/shared/api/client'

/**
 * Le logo de l'organisation.
 *
 * Il se lit en **blob** et non par une URL posée dans `src` : la route est
 * authentifiée, et un `<img src>` partirait sans en-tête pour revenir en 401.
 * Le fichier est donc récupéré par le client HTTP, puis republié au navigateur
 * sous forme d'URL d'objet.
 */
export const organizationLogoApi = {
  read: (organizationId: string) => api.blob(`/organizations/${organizationId}/logo`),

  upload: (organizationId: string, file: File) => {
    const body = new FormData()
    body.append('logo', file)

    return api.upload<{ data: { hasLogo: boolean } }>(
      `/organizations/${organizationId}/logo`,
      body,
    )
  },

  remove: (organizationId: string) =>
    api.delete<{ data: { hasLogo: boolean } }>(`/organizations/${organizationId}/logo`),
}

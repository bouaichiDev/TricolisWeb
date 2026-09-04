import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'

export interface PlatformConfiguration {
  hasDefaultLogo: boolean
}

/**
 * La configuration de l'installation.
 *
 * **Lire est ouvert, écrire ne l'est pas.** La barre latérale de chacun demande
 * s'il y a un logo par défaut ; protéger cette question obligerait à distribuer
 * une permission plateforme pour afficher une image de marque. Le dépôt et le
 * retrait exigent `platform_settings.update`, que seul un rôle plateforme porte.
 *
 * Un seul réglage aujourd'hui. La forme — un objet, pas un booléen nu — est
 * choisie pour le suivant : ajouter un champ ne changera ni l'appel, ni sa clé
 * de cache, ni les écrans qui la lisent.
 */
export const configurationApi = {
  read: () =>
    api.get<ApiResource<PlatformConfiguration>>('/configuration').then((response) => response.data),

  readLogo: () => api.blob('/configuration/logo'),

  uploadLogo: (file: File) => {
    const body = new FormData()
    body.append('logo', file)

    return api.upload<ApiResource<PlatformConfiguration>>('/configuration/logo', body)
  },

  removeLogo: () => api.delete<ApiResource<PlatformConfiguration>>('/configuration/logo'),
}

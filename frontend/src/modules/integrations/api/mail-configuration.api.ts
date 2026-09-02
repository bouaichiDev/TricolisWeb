import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'

import type { MailConfiguration, MailConfigurationPayload } from '../types/mailConfiguration'

/**
 * Ressource **unique** : une organisation n'a qu'une identité d'expédition.
 *
 * D'où un `PUT` sans identifiant, qui crée ou remplace, plutôt qu'un `POST`
 * qui empilerait des boîtes dont aucune ne serait manifestement la bonne.
 */
export const mailConfigurationApi = {
  /** Rend `null` quand rien n'est réglé : l'absence est un état normal. */
  show: () =>
    api
      .get<ApiResource<MailConfiguration | null>>('/mail-configuration')
      .then((response) => response.data),

  save: (payload: MailConfigurationPayload) =>
    api
      .put<ApiResource<MailConfiguration>>('/mail-configuration', payload)
      .then((response) => response.data),

  remove: () => api.delete<void>('/mail-configuration'),

  /**
   * Envoie un courrier d'essai.
   *
   * Sans lui, la première preuve qu'un réglage est faux est une facture qui
   * n'arrive pas.
   */
  test: (recipient?: string) =>
    api
      .post<ApiResource<{ recipient: string }>>('/mail-configuration/test', { recipient })
      .then((response) => response.data),
}

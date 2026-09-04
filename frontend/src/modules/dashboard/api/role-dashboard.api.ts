import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'
import { roleDashboardConfigurationSchema } from '../schemas/roleDashboardSchema'
import type { RoleDashboardWidget, RoleDashboardWidgetSelection } from '../types/dashboard'

/**
 * Le tableau de bord d'un rôle : lecture, enregistrement, réinitialisation.
 *
 * `PUT` et non `PATCH` : la liste envoyée **est** la configuration, widgets
 * décochés compris — par leur absence. Une mise à jour partielle aurait demandé
 * de distinguer « je ne parle pas de ce widget » de « je le retire », ce qu'une
 * liste de clés ne sait pas dire.
 *
 * `reset` supprime la configuration au lieu de l'envoyer vide : une liste vide
 * dit « ce rôle ne voit rien », l'absence de configuration dit « rien de
 * choisi », et c'est cette seconde qui rend les défauts du catalogue.
 *
 * Les trois appels renvoient le réglage entier, ce qui évite de recharger après
 * coup — et de montrer un instant une liste qui ne reflète plus ce qu'on vient
 * d'enregistrer.
 */
export const roleDashboardApi = {
  get: (roleId: string) =>
    api
      .get<ApiResource<RoleDashboardWidget[]>>(`/roles/${roleId}/dashboard`)
      .then((response) => response.data),

  /**
   * Le corps passe par le schéma avant de partir.
   *
   * Pas pour la sécurité — le serveur revalide tout, et c'est lui qui protège —
   * mais pour que le jour où un champ de trop se glisse dans le brouillon,
   * l'erreur apparaisse ici plutôt que d'être silencieusement écartée par le
   * serveur, en laissant croire qu'elle a été prise en compte.
   */
  update: (roleId: string, widgets: RoleDashboardWidgetSelection[]) =>
    api
      .put<ApiResource<RoleDashboardWidget[]>>(
        `/roles/${roleId}/dashboard`,
        roleDashboardConfigurationSchema.parse({ widgets }),
      )
      .then((response) => response.data),

  reset: (roleId: string) =>
    api
      .delete<ApiResource<RoleDashboardWidget[]>>(`/roles/${roleId}/dashboard`)
      .then((response) => response.data),
}

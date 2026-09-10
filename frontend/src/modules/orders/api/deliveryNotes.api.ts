import { api } from '@/shared/api/client'
import type { Document } from '@/modules/documents/types/document'
import type { ApiResource } from '@/shared/api/types'

/**
 * Un modèle de BL applicable à un service — `DeliveryNoteTemplateResource`.
 *
 * Ni corps ni variables : l'écran de génération n'affiche pas la mise en page,
 * il la rend. Ce qui lui faut, c'est de quoi reconnaître le modèle et de quoi
 * comprendre **pourquoi** il est proposé — sa portée.
 */
export interface DeliveryNoteTemplateOption {
  id: string
  code: string
  name: string
  scope: 'global' | 'customer'
  customerId: string | null
  serviceId: string | null
  language: string
  isDefault: boolean
}

/** Le document rendu, tel que la génération le mettra sur papier. */
export interface DeliveryNoteDocument {
  html: string
  number: string
  templateId: string
  templateCode: string
  templateName: string
  scope: 'global' | 'customer'
}

/**
 * Le bon de livraison d'un service de commande.
 *
 * Trois routes, trois gestes : les modèles applicables, l'aperçu, le PDF. Le
 * HTML vient **du serveur** : le reconstruire en JavaScript montrerait un
 * aperçu différent du papier remis au client, et l'écart ne se verrait
 * qu'après la remise.
 */
export const deliveryNotesApi = {
  templates: (orderId: string, serviceId: string) =>
    api.get<{ data: DeliveryNoteTemplateOption[]; meta: { defaultTemplateId: string | null } }>(
      `/orders/${orderId}/services/${serviceId}/delivery-note/templates`,
    ),

  document: (orderId: string, serviceId: string, templateId?: string) =>
    api
      .get<ApiResource<DeliveryNoteDocument>>(
        `/orders/${orderId}/services/${serviceId}/delivery-note`,
        { query: { templateId } },
      )
      .then((response) => response.data),

  generate: (orderId: string, serviceId: string, templateId?: string) =>
    api
      .post<ApiResource<Document>>(`/orders/${orderId}/services/${serviceId}/delivery-note`, {
        templateId,
      })
      .then((response) => response.data),
}

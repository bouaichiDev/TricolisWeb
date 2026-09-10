import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { entityDocumentKeys } from '@/modules/documents/hooks/useEntityDocuments'
import { orderDocumentKeys } from '@/modules/documents/hooks/useOrderDocuments'
import { deliveryNotesApi } from '../api/deliveryNotes.api'

export const deliveryNoteKeys = {
  all: ['delivery-notes'] as const,
  templates: (orderId: string, serviceId: string) =>
    [...deliveryNoteKeys.all, 'templates', orderId, serviceId] as const,
  document: (orderId: string, serviceId: string, templateId: string | undefined) =>
    [...deliveryNoteKeys.all, 'document', orderId, serviceId, templateId ?? 'default'] as const,
}

/** Les modèles de BL que ce service peut employer, le premier étant le défaut. */
export function useDeliveryNoteTemplates(
  orderId: string,
  serviceId: string | null,
  enabled = true,
) {
  return useQuery({
    queryKey: deliveryNoteKeys.templates(orderId, serviceId ?? ''),
    queryFn: () => deliveryNotesApi.templates(orderId, serviceId ?? ''),
    enabled: enabled && serviceId !== null,
  })
}

/**
 * L'aperçu, rendu par le serveur avec le modèle demandé.
 *
 * `retry: false` : les deux échecs attendus ici — aucun modèle applicable, ou
 * un modèle dont le rendu échoue — sont des refus définitifs. Réessayer trois
 * fois ne ferait que retarder le message qui dit quoi corriger.
 */
export function useDeliveryNoteDocument(
  orderId: string,
  serviceId: string | null,
  templateId: string | undefined,
  enabled = true,
) {
  return useQuery({
    queryKey: deliveryNoteKeys.document(orderId, serviceId ?? '', templateId),
    queryFn: () => deliveryNotesApi.document(orderId, serviceId ?? '', templateId),
    enabled: enabled && serviceId !== null,
    retry: false,
  })
}

/**
 * La génération du PDF.
 *
 * Elle invalide les documents et la fiche de commande : le bon vient d'entrer
 * dans l'onglet « Documents », et l'y voir apparaître sans recharger la page
 * est la seule preuve qu'il a bien été enregistré.
 */
export function useGenerateDeliveryNote(orderId: string, serviceId: string | null) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (templateId?: string) =>
      deliveryNotesApi.generate(orderId, serviceId ?? '', templateId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: orderDocumentKeys.lists(orderId) })
      void queryClient.invalidateQueries({
        queryKey: entityDocumentKeys.list('order_service', serviceId ?? ''),
      })
      toast.success(t('orders.deliveryNote.generated'))
    },
  })
}

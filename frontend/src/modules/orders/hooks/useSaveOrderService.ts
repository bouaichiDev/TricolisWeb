import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { orderContentApi, type OrderServiceInput } from '../api/orderContent.api'
import { serviceContactsApi } from '../api/serviceContacts.api'
import type { ServiceContactDraft } from '../schemas/orderDraft'
import { contactChanges, contactPayload } from '../schemas/serviceContactChanges'
import type { OrderServiceContact } from '../types/orderDetail'
import { orderKeys } from './useOrders'

export interface SaveOrderServiceVariables {
  /** `null` tant que le service n'existe pas encore. */
  serviceId: string | null
  payload: OrderServiceInput
  /** Contacts tels qu'enregistrés, et tels que saisis. */
  saved: OrderServiceContact[]
  drafts: ServiceContactDraft[]
}

/** Ce qui est déjà enregistré au moment d'un échec. */
export interface SaveProgress {
  serviceId: string
  saved: OrderServiceContact[]
  drafts: ServiceContactDraft[]
}

/**
 * Échec survenu **après** l'enregistrement du service, sur un de ses contacts.
 *
 * Il porte l'avancement : le formulaire s'y recale, pour qu'un nouvel essai ne
 * recrée ni le service ni les contacts déjà passés.
 */
export class ServiceContactsError extends Error {
  readonly reason: unknown
  readonly progress: SaveProgress
  /** Position du contact fautif dans les brouillons ; `null` pour un retrait. */
  readonly contactIndex: number | null

  constructor(reason: unknown, progress: SaveProgress, contactIndex: number | null) {
    super(reason instanceof Error ? reason.message : 'contacts')
    this.name = 'ServiceContactsError'
    this.reason = reason
    this.progress = progress
    this.contactIndex = contactIndex
  }
}

async function saveOrderService(orderId: string, variables: SaveOrderServiceVariables) {
  const { serviceId, payload, saved, drafts } = variables
  const service =
    serviceId === null
      ? await orderContentApi.createService(orderId, payload)
      : await orderContentApi.updateService(orderId, serviceId, payload)

  const progress: SaveProgress = { serviceId: service.id, saved: [...saved], drafts: [...drafts] }
  const changes = contactChanges(saved, drafts)

  const step = async (index: number | null, run: () => Promise<void>) => {
    try {
      await run()
    } catch (cause) {
      throw new ServiceContactsError(cause, progress, index)
    }
  }

  // Retraits d'abord : un contact principal retiré ne gêne plus celui qui le
  // remplace, même si le serveur sait de toute façon rétrograder l'ancien.
  for (const id of changes.removed) {
    await step(null, async () => {
      await serviceContactsApi.remove(orderId, service.id, id)
      progress.saved = progress.saved.filter((contact) => contact.id !== id)
    })
  }

  for (const { index, draft } of changes.updated) {
    await step(index, async () => {
      const contact = await serviceContactsApi.update(
        orderId,
        service.id,
        draft.key,
        contactPayload(draft),
      )
      progress.saved = progress.saved.map((item) => (item.id === contact.id ? contact : item))
    })
  }

  for (const { index, draft } of changes.created) {
    await step(index, async () => {
      const contact = await serviceContactsApi.create(orderId, service.id, contactPayload(draft))
      progress.saved = [...progress.saved, contact]
      // Le brouillon prend l'identifiant du contact créé : il est désormais
      // « enregistré », et ne sera plus recréé.
      progress.drafts = progress.drafts.map((item) =>
        item.key === draft.key ? { ...item, key: contact.id } : item,
      )
    })
  }
}

/**
 * Enregistre un service et ses contacts, d'un seul geste pour l'utilisateur.
 *
 * Le serveur les expose par deux routes distinctes ; la fiche est rafraîchie
 * même en cas d'échec, puisque le service a pu être enregistré avant qu'un
 * contact soit refusé.
 */
export function useSaveOrderService(orderId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (variables: SaveOrderServiceVariables) => saveOrderService(orderId, variables),
    onSuccess: (_, variables) =>
      toast.success(t(variables.serviceId === null ? 'toast.created' : 'toast.updated')),
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: orderKeys.detail(orderId) })
      void queryClient.invalidateQueries({ queryKey: orderKeys.lists() })
    },
  })
}

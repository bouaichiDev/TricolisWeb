import type { Document } from '@/modules/documents/types/document'
import type { CompactUser } from '@/modules/tracking/types/trackingEvent'

/**
 * Preuve de livraison — `ProofOfDeliveryResource`.
 *
 * La signature et la photo sont des `Document` : le diagramme ne connaît ni
 * entité `Signature` ni entité `DeliveryPhoto`, et le §10 interdit d'en créer.
 * Seuls leurs identifiants voyagent en liste ; le détail charge les documents.
 *
 * Aucun `storagePath` n'est exposé, et il ne doit jamais l'être : c'est un
 * chemin de stockage interne, pas une adresse de téléchargement.
 */
export interface ProofOfDelivery {
  id: string
  orderId: string
  orderServiceId: string | null
  tourStopId: string | null
  recipientName: string
  signatureDocumentId: string | null
  photoDocumentId: string | null
  /** Absente de la ressource de liste, présente au détail. */
  remark?: string | null
  deliveredAt: string
  createdBy: string | null
  signatureDocument?: Document
  photoDocument?: Document
  creator?: CompactUser
}

/** Filtres acceptés par `ListProofOfDeliveryRequest`. */
export interface ProofOfDeliveryFilters {
  page: number
  perPage: number
  orderServiceId?: string
  deliveredFrom?: string
  deliveredTo?: string
  sort?: string
  direction?: 'asc' | 'desc'
}

/**
 * Charge utile de `StoreProofOfDeliveryRequest`.
 *
 * Seuls `orderId`, `recipientName` et `deliveredAt` sont requis. La signature et
 * la photo restent facultatives — le §12 l'exige, et le backend les accepte
 * nulles : une livraison peut être constatée sans que le destinataire ait signé.
 */
export interface ProofOfDeliveryPayload {
  orderId: string
  orderServiceId?: string | null
  tourStopId?: string | null
  recipientName: string
  signatureDocumentId?: string | null
  photoDocumentId?: string | null
  remark?: string | null
  deliveredAt: string
}

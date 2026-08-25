import type { CommunicationStatus } from '../types/communication'

/**
 * Ce qu'un statut autorise, recopié de `CommunicationStatus` côté PHP.
 *
 * | Statut | Modifier | Supprimer | File | Réessayer | Annuler |
 * | --- | --- | --- | --- | --- | --- |
 * | `draft` | ✓ | ✓ | ✓ | — | ✓ |
 * | `scheduled` | ✓ | — | ✓ | — | ✓ |
 * | `queued` | — | — | — | — | ✓ |
 * | `failed` | — | — | — | ✓ | — |
 * | les autres | — | — | — | — | — |
 *
 * C'est une **copie**, et le serveur reste l'autorité :
 * `ApplyCommunicationTransition` relit le statut sous verrou et refuse ce que
 * l'écran aurait laissé passer. Le rôle de cette table est d'éviter de proposer
 * une action qui reviendrait en erreur — pas de décider à la place du serveur.
 */
export interface CommunicationAbilities {
  edit: boolean
  remove: boolean
  queue: boolean
  retry: boolean
  cancel: boolean
}

const NONE: CommunicationAbilities = {
  edit: false,
  remove: false,
  queue: false,
  retry: false,
  cancel: false,
}

export function abilitiesOf(status: CommunicationStatus): CommunicationAbilities {
  switch (status) {
    case 'draft':
      return { edit: true, remove: true, queue: true, retry: false, cancel: true }
    case 'scheduled':
      return { edit: true, remove: false, queue: true, retry: false, cancel: true }
    case 'queued':
      return { ...NONE, cancel: true }
    case 'failed':
      return { ...NONE, retry: true }
    default:
      return NONE
  }
}

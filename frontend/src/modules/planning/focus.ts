import type { PoolOrder } from './types/pool'

/**
 * Le point d'une commande vers lequel amener la carte.
 *
 * **La livraison, pas le chargement.** Le chargement se fait au dépôt : toutes
 * les commandes y pointent le même lieu, et y aller n'apprend rien sur celle
 * qu'on regarde. On ne retombe dessus que si la commande n'a que ça — auquel
 * cas c'est bien là que tout se joue.
 *
 * Le serveur dit lesquels sont des chargements, d'après les codes réglés de
 * l'organisation : deux transporteurs ne les nomment pas pareil, et deviner ici
 * ferait diverger l'écran du regroupement au dépôt.
 */
export function deliveryPoint(order: PoolOrder): { latitude: number; longitude: number } | null {
  const placed = order.services.filter(
    (service) => service.latitude !== null && service.longitude !== null,
  )

  const target = placed.find((service) => !service.isLoading) ?? placed[0]

  if (target === undefined) return null

  return { latitude: target.latitude as number, longitude: target.longitude as number }
}

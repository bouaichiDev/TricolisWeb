import type { TrackingEvent } from '../types/trackingEvent'
import type { JourneyStep, TrackingEventDefinition } from '../types/trackingDefinition'

/**
 * Les prestations d'une commande, telles que le parcours a besoin de les
 * connaître : l'identifiant de la prestation posée, et celui du service au
 * catalogue dont elle relève.
 */
export interface CarriedService {
  id: string
  serviceId: string
}

/**
 * Le parcours d'une commande : les étapes configurées, franchies ou à venir.
 *
 * Croise les **définitions actives** de l'organisation avec les événements
 * réellement survenus. C'est ce qui permet de montrer le chemin complet dès la
 * création — créé · chargé · en route · livré — plutôt qu'une liste qui
 * s'allonge sans jamais dire ce qui reste.
 *
 * **Une étape ne regarde que sa propre prestation.** Le rapprochement par le
 * seul code marquait « livré » une commande dont la livraison était encore en
 * route : son chargement, lui, était terminé, et avait publié l'événement sous
 * une configuration où l'étape ne visait encore aucune prestation. Un événement
 * venu d'ailleurs ne franchit plus une étape qui nomme la sienne.
 *
 * **Les étapes d'une prestation absente sont écartées.** Une commande qui n'a
 * qu'une livraison ne doit pas afficher « chargé au dépôt » comme une étape à
 * venir qui n'arrivera jamais. Une étape sans prestation vaut pour toutes.
 */
export function buildJourney(
  definitions: TrackingEventDefinition[],
  events: TrackingEvent[],
  services: CarriedService[] = [],
): JourneyStep[] {
  const carried = new Set(services.map((service) => service.serviceId))

  return definitions
    .filter((definition) => definition.active)
    .filter((definition) => {
      const scope = scopeOf(definition)

      return scope === null || carried.size === 0 || carried.has(scope)
    })
    .sort((a, b) => a.position - b.position || a.code.localeCompare(b.code))
    .map((definition) => {
      const event = events.find((candidate) => claims(definition, candidate, services))

      return {
        definition,
        occurredAt: event?.occurredAt ?? null,
        // Le detail de l'evenement l'emporte sur celui de la definition : il
        // dit ce qui s'est passe, quand l'autre dit ce qui etait prevu.
        description: event?.description ?? definition.description ?? null,
      }
    })
}

/**
 * Événements qu'aucune étape ne revendique.
 *
 * Un événement ajouté à la main, dont la définition a été supprimée, **ou venu
 * d'une prestation que son étape ne vise plus** : il est survenu, et il ne doit
 * pas disparaître de l'écran sans un mot. Il est montré à part, sous le
 * parcours.
 */
export function looseEvents(
  definitions: TrackingEventDefinition[],
  events: TrackingEvent[],
  services: CarriedService[] = [],
): TrackingEvent[] {
  const active = definitions.filter((definition) => definition.active)

  return events.filter((event) => !active.some((definition) => claims(definition, event, services)))
}

/**
 * Cette étape reconnaît-elle cet événement ?
 *
 * Le rapprochement se fait sur le **code** et non sur le statut : un événement
 * conserve ce qui était décrit au moment où il est survenu, et la définition a
 * pu changer de statut déclencheur depuis.
 *
 * S'y ajoute la prestation quand l'étape en nomme une : un « livré » publié par
 * le chargement ne dit rien de la livraison.
 */
function claims(
  definition: TrackingEventDefinition,
  event: TrackingEvent,
  services: CarriedService[],
): boolean {
  if (event.eventType !== definition.code) return false

  const scope = scopeOf(definition)

  if (scope === null) return true

  // Un evenement sans prestation vient d'une commande ou d'un colis : rien ne
  // permet de l'attribuer, et le refuser vaut mieux que l'attribuer a tort.
  if (event.orderServiceId === null) return false

  return services.some(
    (service) => service.id === event.orderServiceId && service.serviceId === scope,
  )
}

/** La prestation que l'étape vise, ou `null` si elle vaut pour toutes. */
function scopeOf(definition: TrackingEventDefinition): string | null {
  return definition.serviceId ?? null
}

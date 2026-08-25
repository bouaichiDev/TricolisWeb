import type { TrackingEvent } from '../types/trackingEvent'
import type { JourneyStep, TrackingEventDefinition } from '../types/trackingDefinition'

/**
 * Le parcours d'une commande : les étapes configurées, franchies ou à venir.
 *
 * Croise les **définitions actives** de l'organisation avec les événements
 * réellement survenus. C'est ce qui permet de montrer le chemin complet dès la
 * création — créé · chargé · en route · livré — plutôt qu'une liste qui
 * s'allonge sans jamais dire ce qui reste.
 *
 * Une étape est franchie s'il existe un événement portant son `code`. Le
 * rapprochement se fait sur le code et non sur le statut : un événement conserve
 * ce qui était décrit au moment où il est survenu, et la définition a pu changer
 * de statut déclencheur depuis.
 */
export function buildJourney(
  definitions: TrackingEventDefinition[],
  events: TrackingEvent[],
): JourneyStep[] {
  const byCode = new Map(events.map((event) => [event.eventType, event]))

  return definitions
    .filter((definition) => definition.active)
    .sort((a, b) => a.position - b.position || a.code.localeCompare(b.code))
    .map((definition) => {
      const event = byCode.get(definition.code)

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
 * Événements sans étape configurée.
 *
 * Un événement ajouté à la main, ou dont la définition a été supprimée, ne doit
 * pas disparaître de l'écran : il est survenu. Il est montré à part, sous le
 * parcours.
 */
export function looseEvents(
  definitions: TrackingEventDefinition[],
  events: TrackingEvent[],
): TrackingEvent[] {
  const codes = new Set(definitions.filter((item) => item.active).map((item) => item.code))

  return events.filter((event) => !codes.has(event.eventType))
}

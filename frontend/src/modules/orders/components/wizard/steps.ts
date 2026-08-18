import type { OrderStep } from '../../schemas/orderErrors'

/**
 * Ordre du parcours de création.
 *
 * Cinq étapes : le modèle ne comporte pas d'entité « arrêt », l'adresse et le
 * créneau étant portés par chaque service.
 */
export const ORDER_STEPS: OrderStep[] = ['general', 'lines', 'packages', 'services', 'review']

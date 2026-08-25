/**
 * Une étape du parcours client — `TrackingEventDefinitionResource`.
 *
 * Elle dit quel couple (entité, statut) devient visible pour le client, sous
 * quel titre et à quelle place. Le chauffeur pose le statut, l'étape apparaît :
 * personne ne la saisit.
 *
 * `isLive` est **dérivé** de `apiConfigurationId` côté serveur : une étape est
 * suivie en direct si une API la renseigne, et on sait laquelle.
 */
export interface TrackingEventDefinition {
  id: string
  organizationId: string
  sourceType: string
  statusCode: string
  code: string
  title: string
  description: string | null
  icon: string | null
  position: number
  apiConfigurationId: string | null
  isLive: boolean
  active: boolean
  createdAt: string
  updatedAt: string
}

export interface TrackingDefinitionFilters {
  page: number
  perPage: number
  search?: string
}

/** Charge utile de `StoreTrackingEventDefinitionRequest`. */
export interface TrackingDefinitionPayload {
  sourceType: string
  statusCode: string
  code: string
  title: string
  description?: string | null
  icon?: string | null
  position?: number
  apiConfigurationId?: string | null
  active?: boolean
}

/**
 * Une étape du parcours d'une commande, franchie ou à venir.
 *
 * Croisement des définitions actives et des `TrackingEvent` de la commande :
 * c'est ce qui permet d'afficher le parcours complet dès la création — créé,
 * chargé, en route, livré — plutôt qu'une liste qui s'allonge sans dire ce qui
 * reste.
 */
export interface JourneyStep {
  definition: TrackingEventDefinition
  /** Date de franchissement, `null` tant que l'étape est à venir. */
  occurredAt: string | null
  description: string | null
}

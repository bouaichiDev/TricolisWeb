/**
 * Charge utile de `POST /orders` — création transactionnelle complète.
 *
 * Le backend crée en une transaction la commande, ses lignes, ses colis, leurs
 * liaisons, ses services, leurs contacts et leurs colis. Le §33 interdit de
 * créer les sous-ressources une par une pendant le formulaire.
 *
 * **Les liaisons internes passent par des clés temporaires**, pas par des
 * identifiants : les lignes n'existent pas encore quand les colis les
 * référencent.
 *
 * Le backend traite les deux différemment, et il faut le savoir :
 *
 * - **les colis acceptent une clé libre.** `CreateOrderPackages` indexe le
 *   résultat par `$package->key` en plus de l'index. `parentKey` et
 *   `packageKey` peuvent donc porter un identifiant stable ;
 * - **les lignes non.** `CreateOrderLines` n'indexe que par `(string) $index`
 *   et par identifiant : il n'existe pas de `lines[].key`. `lineKey` doit donc
 *   valoir la **position de la ligne dans le tableau envoyé**.
 *
 * Le §23 interdit l'index comme identité temporaire — et il a raison pour
 * l'état du formulaire, où retirer une ligne décalerait tout. La conciliation :
 * le formulaire garde un identifiant stable par ligne (`crypto.randomUUID()`),
 * et la position n'est calculée **qu'au moment de sérialiser**, sur le tableau
 * définitif. L'index ne sert jamais d'identité en mémoire.
 *
 * Un colis parent doit par ailleurs précéder ses enfants dans le tableau :
 * `CreateOrderPackages` construit son index au fil de la boucle.
 */
export interface OrderLinePayload {
  catalogItemId?: string | null
  name?: string | null
  articleCode?: string | null
  barcode?: string | null
  externalReference?: string | null
  description?: string | null
  quantity: number
  weight?: number
  volume?: number
  length?: number | null
  width?: number | null
  height?: number | null
  purchasePrice?: number | null
  sellingPrice?: number | null
}

export interface OrderPackagePayload {
  key: string
  parentKey?: string | null
  packageTypeId?: string | null
  groupingTypeId?: string | null
  barcode?: string | null
  reference?: string | null
  description?: string | null
  quantity?: number
  weight?: number
  volume?: number
  lines?: { lineKey: string; quantity: number }[]
}

export interface OrderServiceContactPayload {
  contactId?: string | null
  contactRole?: string
  isPrimary?: boolean
  firstName?: string | null
  lastName?: string | null
  phone?: string | null
  mobile?: string | null
  email?: string | null
}

/**
 * Les quatre montants sont `required` côté serveur.
 *
 * Le §29 interdit d'y poser `0` silencieusement : ce sont des valeurs métier,
 * elles sont saisies.
 */
export interface OrderServicePayload {
  serviceId: string
  addressId: string
  serviceNumber: string
  sequence: number
  requestedDate: string
  requestedFrom?: string | null
  requestedTo?: string | null
  quantity: number
  unit: string
  requiredTimeMinutes: number
  remainingTimeMinutes: number
  weight: number
  volume: number
  packageCount: number
  customerUnitPrice: number
  customerTotalPrice: number
  providerUnitCost: number
  providerTotalCost: number
  instructions?: string | null
  status: string
  contacts?: OrderServiceContactPayload[]
  packages?: { packageKey: string; quantity?: number; handlingInstructions?: string | null }[]
}

export interface CreateOrderPayload {
  customerId: string
  agencyId: string
  depotId?: string | null
  externalReference?: string | null
  customerReference?: string | null
  orderType?: string | null
  groupCode?: string | null
  orderDate: string
  source?: string
  currencyCode?: string
  internalRemark?: string | null
  workerRemark?: string | null
  lines: OrderLinePayload[]
  packages?: OrderPackagePayload[]
  services: OrderServicePayload[]
}

/**
 * Modification d'une commande.
 *
 * Ni `customerId` ni `agencyId` : `UpdateOrderRequest` ne les accepte pas —
 * une commande ne change ni de client ni d'agence après création.
 */
export interface UpdateOrderPayload {
  depotId?: string | null
  externalReference?: string | null
  customerReference?: string | null
  orderType?: string | null
  groupCode?: string | null
  orderDate?: string
  currencyCode?: string
  internalRemark?: string | null
  workerRemark?: string | null
}

/** Options réellement acceptées par `DuplicateOrderRequest`. */
export interface DuplicateOrderPayload {
  lines?: boolean
  packages?: boolean
  services?: boolean
  contacts?: boolean
  documents?: boolean
}

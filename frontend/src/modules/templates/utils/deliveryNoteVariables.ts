/**
 * Les chemins qu'un modèle de bon de livraison peut nommer.
 *
 * Recopiés de `DeliveryNotePaths` — un test côté serveur tient cette liste et
 * le contexte de rendu identiques. Proposer ici un chemin que le serveur ne
 * fournit pas ferait échouer le BL au moment de le générer, devant le client.
 *
 * `packages` et `articles` sont des **listes** : elles se parcourent par une
 * section, pas par un remplacement. L'écran le dit à l'insertion, pour éviter
 * un `{{ packages }}` qui ne rendrait rien.
 */
export const DELIVERY_NOTE_SCALAR_PATHS = [
  'deliveryNote.number',
  'deliveryNote.issuedAt',
  'deliveryNote.issuedAtTime',
  'organization.code',
  'organization.name',
  'organization.legalName',
  'organization.registrationNumber',
  'organization.taxNumber',
  'organization.email',
  'organization.phone',
  'organization.logo',
  'orderer.code',
  'orderer.name',
  'orderer.legalName',
  'orderer.email',
  'orderer.phone',
  'order.orderNumber',
  'order.orderDate',
  'order.orderType',
  'order.externalReference',
  'order.customerReference',
  'order.groupCode',
  'order.currencyCode',
  'order.remark',
  'service.serviceNumber',
  'service.code',
  'service.name',
  'service.sequence',
  'service.quantity',
  'service.unit',
  'service.requestedDate',
  'service.requestedFrom',
  'service.requestedTo',
  'service.weight',
  'service.volume',
  'service.packageCount',
  'service.instructions',
  'service.status',
  'loading.agencyCode',
  'loading.agencyName',
  'loading.point',
  'loading.depotCode',
  'loading.depotName',
  'loading.email',
  'loading.phone',
  'loading.addressCode',
  'loading.name',
  'loading.addressLine1',
  'loading.addressLine2',
  'loading.postalCode',
  'loading.city',
  'loading.country',
  'loading.instructions',
  'delivery.contactName',
  'delivery.contactPhone',
  'delivery.contactEmail',
  'delivery.addressCode',
  'delivery.name',
  'delivery.addressLine1',
  'delivery.addressLine2',
  'delivery.postalCode',
  'delivery.city',
  'delivery.country',
  'delivery.instructions',
  'totals.packageCount',
  'totals.articleCount',
  'totals.packageQuantity',
  'totals.articleQuantity',
  'totals.weight',
  'totals.volume',
] as const

/** Les deux chemins sur lesquels une section se répète. */
export const DELIVERY_NOTE_PACKAGES_PATH = 'packages'

export const DELIVERY_NOTE_ARTICLES_PATH = 'articles'

/** Champs disponibles **à l'intérieur** d'une section sur les colis. */
export const DELIVERY_NOTE_PACKAGE_PATHS = [
  'packages.reference',
  'packages.barcode',
  'packages.description',
  'packages.packageType',
  'packages.groupingType',
  'packages.quantity',
  'packages.weight',
  'packages.volume',
  'packages.length',
  'packages.width',
  'packages.height',
  'packages.handlingInstructions',
  'packages.status',
] as const

/** Champs disponibles **à l'intérieur** d'une section sur les articles. */
export const DELIVERY_NOTE_ARTICLE_PATHS = [
  'articles.articleCode',
  'articles.barcode',
  'articles.name',
  'articles.description',
  'articles.externalReference',
  'articles.quantity',
  'articles.orderedQuantity',
  'articles.weight',
  'articles.volume',
  'articles.packageReference',
  'articles.packageBarcode',
] as const

export const DELIVERY_NOTE_PATHS: string[] = [
  ...DELIVERY_NOTE_SCALAR_PATHS,
  DELIVERY_NOTE_PACKAGES_PATH,
  ...DELIVERY_NOTE_PACKAGE_PATHS,
  DELIVERY_NOTE_ARTICLES_PATH,
  ...DELIVERY_NOTE_ARTICLE_PATHS,
]

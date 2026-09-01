/**
 * Les variables qu'une communication de commande peut nommer.
 *
 * Recopiées d'`OrderCommunicationContext::build()`. Sans cette liste, il
 * fallait deviner : l'aide de l'écran montrait `{{orderNumber}}`, la fabrique
 * du serveur écrivait `order_number`, et un modèle déclarant un nom que le
 * serveur ne fournit pas échoue au moment de l'envoi — trop tard.
 *
 * Le serveur accepte les deux graphies : la correspondance ignore casse et
 * tirets bas. La liste emploie la graphie du serveur ; écrire `orderNumber`
 * fonctionne tout autant.
 */
export const ORDER_VARIABLES = [
  'order_number',
  'order_status',
  'order_type',
  'order_source',
  'order_date',
  'external_reference',
  'customer_reference',
  'group_code',
  'currency_code',
  'weight',
  'volume',
  'package_count',
  'customer_code',
  'customer_name',
  'agency_name',
] as const

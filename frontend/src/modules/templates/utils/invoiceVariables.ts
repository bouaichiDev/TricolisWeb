/**
 * Les chemins qu'un modèle de facture peut nommer.
 *
 * Recopiés de `InvoiceRenderContext::availablePaths()`. Le §21 veut que
 * l'utilisateur insère une variable sans en mémoriser la syntaxe ; le §22
 * interdit d'en inventer une que le serveur ne saurait pas résoudre.
 *
 * `invoice.lines` est une **liste** : elle se parcourt par une section, pas par
 * un remplacement. La liste ci-dessous le dit, pour que l'écran propose la
 * bonne syntaxe.
 */
export const INVOICE_SCALAR_PATHS = [
  'invoice.invoiceNumber',
  'invoice.invoiceDate',
  'invoice.periodFrom',
  'invoice.periodTo',
  'invoice.currencyCode',
  'invoice.subtotal',
  'invoice.taxTotal',
  'invoice.total',
  'invoice.externalReference',
  'invoice.remark',
  'customer.code',
  'customer.name',
  'customer.legalName',
  'customer.email',
  'customer.phone',
  'organization.code',
  'organization.name',
  'organization.email',
  'organization.phone',
] as const

/** Le chemin de la liste : celui sur lequel une section se répète. */
export const INVOICE_LINES_PATH = 'invoice.lines'

/** Champs disponibles **à l'intérieur** d'une section sur les lignes. */
export const INVOICE_LINE_PATHS = [
  'invoice.lines.lineNumber',
  'invoice.lines.serviceCode',
  'invoice.lines.description',
  'invoice.lines.customerOrderReference',
  'invoice.lines.quantity',
  'invoice.lines.unitPrice',
  'invoice.lines.discountRate',
  'invoice.lines.taxRate',
  'invoice.lines.totalExcludingTax',
  'invoice.lines.totalIncludingTax',
  'invoice.lines.serviceCompletedAt',
  'invoice.lines.address.name',
  'invoice.lines.address.addressLine1',
  'invoice.lines.address.postalCode',
  'invoice.lines.address.city',
  'invoice.lines.address.country',
] as const

export const INVOICE_PATHS: string[] = [
  ...INVOICE_SCALAR_PATHS,
  INVOICE_LINES_PATH,
  ...INVOICE_LINE_PATHS,
]

/**
 * Mise en page de départ d'un modèle de facture.
 *
 * Proposée à la création plutôt qu'une page blanche : écrire un document
 * complet à la main, en devinant les chemins, est le meilleur moyen d'obtenir
 * un rendu qui échoue à la première clôture.
 *
 * Elle emploie les deux syntaxes du moteur — remplacement et section — pour
 * qu'elles se copient plutôt qu'elles ne se mémorisent.
 */
export const INVOICE_STARTER_BODY = `<h1>Facture {{ invoice.invoiceNumber }}</h1>
<p>{{ organization.name }}</p>
<p>{{ customer.name }} — {{ invoice.invoiceDate }}</p>

<table>
  <tr><th>#</th><th>Prestation</th><th>Montant HT</th></tr>
  {{#invoice.lines}}
  <tr>
    <td>{{ invoice.lines.lineNumber }}</td>
    <td>{{ invoice.lines.description }}</td>
    <td>{{ invoice.lines.totalExcludingTax }}</td>
  </tr>
  {{/invoice.lines}}
</table>

<p>Total HT : {{ invoice.subtotal }} {{ invoice.currencyCode }}</p>
<p>TVA : {{ invoice.taxTotal }}</p>
<p><strong>Total : {{ invoice.total }} {{ invoice.currencyCode }}</strong></p>
`

/** Les chemins employés par la mise en page de départ, à déclarer avec elle. */
export const INVOICE_STARTER_VARIABLES = [
  'invoice.invoiceNumber',
  'invoice.invoiceDate',
  'invoice.subtotal',
  'invoice.taxTotal',
  'invoice.total',
  'invoice.currencyCode',
  'customer.name',
  'organization.name',
  'invoice.lines',
  'invoice.lines.lineNumber',
  'invoice.lines.description',
  'invoice.lines.totalExcludingTax',
]

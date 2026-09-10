/**
 * Mise en page de départ d'un bon de livraison.
 *
 * Proposée à la création plutôt qu'une page blanche : écrire un BL complet à la
 * main, en devinant les chemins, est le meilleur moyen d'obtenir un rendu qui
 * échoue devant le client. Elle porte les huit blocs demandés — en-tête et
 * logo, donneur d'ordre, chargement, livraison, tableau des colis, tableau des
 * articles, observations, signature et pied de page — et emploie les deux
 * syntaxes du moteur, pour qu'elles se copient plutôt qu'elles ne se
 * mémorisent.
 *
 * Le style est **en ligne**, sans feuille externe : le PDF est produit par
 * dompdf, qui n'a ni session ni réseau pour aller chercher un fichier au moment
 * du rendu. Une `<link>` y serait ignorée en silence, et le bon sortirait nu.
 */
export const DELIVERY_NOTE_STARTER_BODY = `<div style="font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111">

  <table style="width:100%; border-bottom: 2px solid #111; padding-bottom: 8px">
    <tr>
      <td style="width:60%">
        <img src="{{ organization.logo }}" style="max-height:56px" />
        <div style="font-size:13px; font-weight:bold">{{ organization.name }}</div>
        <div>{{ organization.legalName }}</div>
        <div>{{ organization.email }} · {{ organization.phone }}</div>
      </td>
      <td style="width:40%; text-align:right; vertical-align:top">
        <div style="font-size:18px; font-weight:bold">BON DE LIVRAISON</div>
        <div>N° {{ deliveryNote.number }}</div>
        <div>Le {{ deliveryNote.issuedAt }}</div>
        <div>Commande {{ order.orderNumber }}</div>
        <div>Prestation {{ service.serviceNumber }} — {{ service.name }}</div>
      </td>
    </tr>
  </table>

  <table style="width:100%; margin-top:12px">
    <tr>
      <td style="width:33%; vertical-align:top; padding-right:8px">
        <div style="font-weight:bold; text-transform:uppercase">Donneur d'ordre</div>
        <div>{{ orderer.name }}</div>
        <div>{{ orderer.legalName }}</div>
        <div>{{ orderer.phone }}</div>
        <div>Réf. client : {{ order.customerReference }}</div>
      </td>
      <td style="width:33%; vertical-align:top; padding-right:8px">
        <div style="font-weight:bold; text-transform:uppercase">Chargement</div>
        <div>{{ loading.agencyName }}</div>
        <div>{{ loading.addressLine1 }}</div>
        <div>{{ loading.postalCode }} {{ loading.city }}</div>
        <div>Quai : {{ loading.point }}</div>
      </td>
      <td style="width:34%; vertical-align:top">
        <div style="font-weight:bold; text-transform:uppercase">Livraison</div>
        <div>{{ delivery.name }}</div>
        <div>{{ delivery.addressLine1 }}</div>
        <div>{{ delivery.postalCode }} {{ delivery.city }}</div>
        <div>{{ delivery.contactName }} · {{ delivery.contactPhone }}</div>
        <div>Le {{ service.requestedDate }} entre {{ service.requestedFrom }} et {{ service.requestedTo }}</div>
      </td>
    </tr>
  </table>

  <div style="font-weight:bold; text-transform:uppercase; margin-top:14px">Colis</div>
  <table style="width:100%; border-collapse:collapse" border="1" cellpadding="4">
    <tr style="background:#eee">
      <th align="left">Référence</th><th align="left">Code-barres</th><th align="left">Type</th>
      <th align="right">Qté</th><th align="right">Poids</th><th align="right">Volume</th>
    </tr>
    {{#packages}}
    <tr>
      <td>{{ packages.reference }}</td>
      <td>{{ packages.barcode }}</td>
      <td>{{ packages.packageType }}</td>
      <td align="right">{{ packages.quantity }}</td>
      <td align="right">{{ packages.weight }}</td>
      <td align="right">{{ packages.volume }}</td>
    </tr>
    {{/packages}}
  </table>

  <div style="font-weight:bold; text-transform:uppercase; margin-top:14px">Articles</div>
  <table style="width:100%; border-collapse:collapse" border="1" cellpadding="4">
    <tr style="background:#eee">
      <th align="left">Code</th><th align="left">Désignation</th>
      <th align="left">Colis</th><th align="right">Qté</th>
    </tr>
    {{#articles}}
    <tr>
      <td>{{ articles.articleCode }}</td>
      <td>{{ articles.name }}</td>
      <td>{{ articles.packageReference }}</td>
      <td align="right">{{ articles.quantity }}</td>
    </tr>
    {{/articles}}
  </table>

  <div style="margin-top:8px">
    Total : {{ totals.packageCount }} colis, {{ totals.articleCount }} lignes,
    {{ totals.weight }} kg, {{ totals.volume }} m³.
  </div>

  <div style="margin-top:14px">
    <div style="font-weight:bold; text-transform:uppercase">Observations</div>
    <div>{{ service.instructions }}</div>
    <div>{{ order.remark }}</div>
  </div>

  <table style="width:100%; margin-top:24px">
    <tr>
      <td style="width:50%; border:1px solid #111; height:90px; vertical-align:top; padding:6px">
        Le transporteur
      </td>
      <td style="width:50%; border:1px solid #111; height:90px; vertical-align:top; padding:6px">
        Le destinataire — nom, date et signature
      </td>
    </tr>
  </table>

  <div style="margin-top:12px; border-top:1px solid #999; padding-top:6px; font-size:9px; color:#666">
    {{ organization.legalName }} — RC {{ organization.registrationNumber }} —
    ICE {{ organization.taxNumber }} — {{ organization.email }}
  </div>
</div>
`

/** Les chemins employés par la mise en page de départ, à déclarer avec elle. */
export const DELIVERY_NOTE_STARTER_VARIABLES = [
  'deliveryNote.number',
  'deliveryNote.issuedAt',
  'organization.name',
  'organization.legalName',
  'organization.registrationNumber',
  'organization.taxNumber',
  'organization.email',
  'organization.phone',
  'organization.logo',
  'orderer.name',
  'orderer.legalName',
  'orderer.phone',
  'order.orderNumber',
  'order.customerReference',
  'order.remark',
  'service.serviceNumber',
  'service.name',
  'service.requestedDate',
  'service.requestedFrom',
  'service.requestedTo',
  'service.instructions',
  'loading.agencyName',
  'loading.addressLine1',
  'loading.postalCode',
  'loading.city',
  'loading.point',
  'delivery.name',
  'delivery.addressLine1',
  'delivery.postalCode',
  'delivery.city',
  'delivery.contactName',
  'delivery.contactPhone',
  'packages',
  'packages.reference',
  'packages.barcode',
  'packages.packageType',
  'packages.quantity',
  'packages.weight',
  'packages.volume',
  'articles',
  'articles.articleCode',
  'articles.name',
  'articles.packageReference',
  'articles.quantity',
  'totals.packageCount',
  'totals.articleCount',
  'totals.weight',
  'totals.volume',
]

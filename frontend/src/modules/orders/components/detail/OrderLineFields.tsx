import { useTranslation } from 'react-i18next'

import { DetailField } from '@/shared/components/layout/DetailField'

import type { OrderLine } from '../../types/orderDetail'

const show = (value: number | string | null): string | undefined =>
  value === null ? undefined : String(value)

/**
 * Tous les champs d'une ligne de commande, tels que le diagramme les déclare.
 *
 * Trois quantités suivies distinctes de la quantité commandée — réservée,
 * préparée, livrée — appartiennent aux modules Stock et Exploitation : elles
 * sont affichées en lecture seule, jamais saisies ici, et restent vides tant
 * que ces modules n'ont rien écrit.
 *
 * `parentLineId` existe au diagramme : une ligne peut en composer une autre.
 * Rien ne la renseigne encore, mais la masquer ferait croire qu'elle n'existe
 * pas.
 */
export function OrderLineFields({ line }: { line: OrderLine }) {
  const { t } = useTranslation()

  return (
    <dl className="grid gap-x-4 gap-y-1 sm:grid-cols-2 lg:grid-cols-4">
      <DetailField label={t('orders.fields.name')}>{line.name}</DetailField>
      <DetailField label={t('orders.fields.articleCode')}>{line.articleCode}</DetailField>
      <DetailField label={t('orders.fields.barcode')}>{line.barcode}</DetailField>
      <DetailField label={t('orders.fields.externalReference')}>
        {line.externalReference}
      </DetailField>

      <DetailField label={t('orders.fields.quantity')}>{show(line.quantity)}</DetailField>
      <DetailField label={t('orders.fields.reservedQuantity')}>
        {show(line.reservedQuantity)}
      </DetailField>
      <DetailField label={t('orders.fields.preparedQuantity')}>
        {show(line.preparedQuantity)}
      </DetailField>
      <DetailField label={t('orders.fields.deliveredQuantity')}>
        {show(line.deliveredQuantity)}
      </DetailField>

      <DetailField label={t('orders.fields.weight')}>{show(line.weight)}</DetailField>
      <DetailField label={t('orders.fields.volume')}>{show(line.volume)}</DetailField>
      <DetailField label={t('orders.fields.length')}>{show(line.length)}</DetailField>
      <DetailField label={t('orders.fields.width')}>{show(line.width)}</DetailField>

      <DetailField label={t('orders.fields.height')}>{show(line.height)}</DetailField>
      <DetailField label={t('orders.fields.purchasePrice')}>
        {show(line.purchasePrice)}
      </DetailField>
      <DetailField label={t('orders.fields.sellingPrice')}>{show(line.sellingPrice)}</DetailField>
      <DetailField label={t('orders.fields.status')}>{line.status}</DetailField>

      <DetailField label={t('orders.fields.parentLine')}>{line.parentLineId}</DetailField>
      <DetailField label={t('orders.fields.catalogItem')}>{line.catalogItemId}</DetailField>
      <div className="sm:col-span-2">
        <DetailField label={t('orders.fields.description')}>{line.description}</DetailField>
      </div>
    </dl>
  )
}

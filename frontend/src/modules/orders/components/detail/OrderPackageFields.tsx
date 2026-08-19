import { useTranslation } from 'react-i18next'

import { DetailField } from '@/shared/components/layout/DetailField'

import type { OrderPackage } from '../../types/orderDetail'

const show = (value: number | string | null): string | undefined =>
  value === null ? undefined : String(value)

interface OrderPackageFieldsProps {
  pkg: OrderPackage
  /** Libellé du colis parent, quand il en a un. */
  parentLabel?: string
}

/**
 * Tous les champs d'un colis, tels que le diagramme les déclare.
 *
 * `currentStockLocationId` en fait partie : la colonne existe depuis la
 * création de la table. L'emplacement lui-même relève du module Stock, phase
 * ultérieure — seul l'identifiant est connu ici, et il reste vide tant que rien
 * ne l'a renseigné.
 */
export function OrderPackageFields({ pkg, parentLabel }: OrderPackageFieldsProps) {
  const { t } = useTranslation()

  return (
    <dl className="grid gap-x-4 gap-y-1 sm:grid-cols-2 lg:grid-cols-4">
      <DetailField label={t('orders.fields.reference')}>{pkg.reference}</DetailField>
      <DetailField label={t('orders.fields.barcode')}>{pkg.barcode}</DetailField>
      <DetailField label={t('orders.packages.packageType')}>{pkg.packageType?.name}</DetailField>
      <DetailField label={t('orders.packages.groupingType')}>
        {pkg.groupingType?.name}
      </DetailField>

      <DetailField label={t('orders.fields.quantity')}>{show(pkg.quantity)}</DetailField>
      <DetailField label={t('orders.fields.weight')}>{show(pkg.weight)}</DetailField>
      <DetailField label={t('orders.fields.volume')}>{show(pkg.volume)}</DetailField>
      <DetailField label={t('orders.fields.status')}>{pkg.status}</DetailField>

      <DetailField label={t('orders.fields.length')}>{show(pkg.length)}</DetailField>
      <DetailField label={t('orders.fields.width')}>{show(pkg.width)}</DetailField>
      <DetailField label={t('orders.fields.height')}>{show(pkg.height)}</DetailField>
      <DetailField label={t('orders.packages.parent')}>
        {parentLabel ?? pkg.parentPackageId}
      </DetailField>

      <DetailField label={t('orders.fields.stockLocation')}>
        {pkg.currentStockLocationId}
      </DetailField>
      <div className="sm:col-span-2 lg:col-span-3">
        <DetailField label={t('orders.fields.description')}>{pkg.description}</DetailField>
      </div>
    </dl>
  )
}

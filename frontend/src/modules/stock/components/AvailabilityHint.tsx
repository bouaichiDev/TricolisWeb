import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'

import { useStockBalances } from '../hooks/useStockBalances'
import { formatStockQuantity } from '../utils/stockSources'

interface AvailabilityHintProps {
  stockItemId: string
  value: string
  onChange: (stockLocationId: string) => void
  error?: string
}

/**
 * Où il reste de cet article, et combien.
 *
 * Les emplacements viennent des **soldes**, pas de la liste des emplacements :
 * réserver dans un endroit où l'article n'est pas n'a pas de sens, et le serveur
 * le refuserait faute de disponible. Un article présent nulle part donne donc
 * une liste vide, et l'écran le dit.
 *
 * `availableOnly` écarte les emplacements entièrement réservés : ils portent de
 * la marchandise, mais elle est déjà promise ailleurs.
 *
 * Le disponible affiché est **indicatif**. Il peut avoir changé quand la
 * réservation part : `CreateStockReservationAction` le relit sous verrou, et
 * répond 409 s'il ne couvre plus. C'est voulu — l'écran ne peut pas tenir cette
 * promesse à la place de la base.
 */
export function AvailabilityHint({
  stockItemId,
  value,
  onChange,
  error,
}: AvailabilityHintProps) {
  const { t } = useTranslation()

  const { data, isPending } = useStockBalances(
    {
      page: 1,
      perPage: 100,
      stockItemId,
      availableOnly: true,
      sort: 'available_quantity',
      direction: 'desc',
    },
    stockItemId !== '',
  )

  const balances = data?.data ?? []

  const options = balances.map((balance) => ({
    value: balance.stockLocationId,
    label: balance.locationCode ?? balance.stockLocationId,
    hint: t('stock.availableHere', {
      quantity: formatStockQuantity(balance.availableQuantity),
    }),
  }))

  const description =
    stockItemId === ''
      ? t('stock.pickItemFirst')
      : !isPending && options.length === 0
        ? t('stock.nothingAvailable')
        : t('stock.availabilityHint')

  return (
    <AsyncSelect
      label={t('stock.fields.location')}
      value={value}
      onChange={onChange}
      options={options}
      isLoading={stockItemId !== '' && isPending}
      disabled={stockItemId === ''}
      description={description}
      required
      error={error}
    />
  )
}

import { useTranslation } from 'react-i18next'

import { formatAdjustmentLocation, type AdjustmentCorrection } from '../../types/stockAdjustment'
import { formatStockQuantity } from '../../utils/stockSources'

/**
 * Le mouvement que l'acceptation va créer, tel que le serveur l'a calculé :
 * sens, quantité, emplacement, type. Rien n'est recalculé ici.
 */
export function CorrectionRecap({
  correction,
  movementType,
}: {
  correction: AdjustmentCorrection
  movementType: string
}) {
  const { t } = useTranslation()

  if (correction.direction === 'none') {
    return (
      <p className="rounded-md border bg-muted/40 p-3 text-sm" data-testid="correction-recap">
        {t('stock.adjustments.recap.none')}
      </p>
    )
  }

  return (
    <dl
      className="grid grid-cols-2 gap-2 rounded-md border bg-muted/40 p-3 text-sm"
      data-testid="correction-recap"
    >
      <dt className="text-muted-foreground">{t('stock.adjustments.recap.direction')}</dt>
      <dd className="font-medium">{t(`stock.adjustments.recap.${correction.direction}`)}</dd>
      <dt className="text-muted-foreground">{t('stock.fields.quantity')}</dt>
      <dd className="font-medium">{formatStockQuantity(correction.quantity)}</dd>
      <dt className="text-muted-foreground">{t('stock.fields.location')}</dt>
      <dd>{formatAdjustmentLocation(correction.location)}</dd>
      <dt className="text-muted-foreground">{t('stock.fields.movementType')}</dt>
      <dd>{movementType}</dd>
      <dt className="text-muted-foreground">{t('stock.adjustments.recap.currentHere')}</dt>
      <dd>
        {t('stock.adjustments.recap.currentValue', {
          quantity: formatStockQuantity(correction.currentQuantity),
          reserved: formatStockQuantity(correction.reservedQuantity),
        })}
      </dd>
    </dl>
  )
}

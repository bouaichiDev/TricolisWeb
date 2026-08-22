import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'

import type { OrderStockPlanLine } from '../types/orderDetail'

interface OrderStockPlanFieldsProps {
  lines: OrderStockPlanLine[]
  isLoading: boolean
  /** Emplacement retenu par ligne, pour les seules lignes ambiguës. */
  choices: Record<string, string>
  onChange: (orderLineId: string, stockLocationId: string) => void
}

/**
 * Ce que la confirmation sortira du stock, et ce qu'il reste à trancher.
 *
 * Une ligne de commande ne dit pas où sa marchandise se trouve. Quand un
 * article dort dans un seul emplacement, le serveur le trouve et il n'y a rien
 * à demander ; quand il en occupe plusieurs, personne d'autre que
 * l'utilisateur ne peut dire lequel vider.
 *
 * L'écran ne montre donc que ce qui demande une décision. Les lignes
 * `resolved`, `untracked` et `consumed` sont silencieuses — les annoncer une à
 * une transformerait une confirmation en inventaire.
 */
export function OrderStockPlanFields({
  lines,
  isLoading,
  choices,
  onChange,
}: OrderStockPlanFieldsProps) {
  const { t } = useTranslation()

  const ambiguous = lines.filter((line) => line.state === 'ambiguous')
  const insufficient = lines.filter((line) => line.state === 'insufficient')
  const resolved = lines.filter((line) => line.state === 'resolved')

  if (isLoading) return <p className="text-sm text-muted-foreground">{t('common.loading')}</p>

  if (ambiguous.length === 0 && insufficient.length === 0) {
    return resolved.length === 0 ? null : (
      <p className="text-sm text-muted-foreground">
        {t('orders.stockPlan.willConsume', { count: resolved.length })}
      </p>
    )
  }

  return (
    <div className="flex flex-col gap-4 border-t pt-4">
      <div>
        <p className="text-sm font-medium">{t('orders.stockPlan.title')}</p>
        <p className="text-xs text-muted-foreground">{t('orders.stockPlan.hint')}</p>
      </div>

      {insufficient.length > 0 ? (
        <Alert variant="destructive">
          <AlertDescription>
            {t('orders.stockPlan.insufficient', {
              articles: insufficient.map((line) => line.articleCode ?? line.name).join(', '),
            })}
          </AlertDescription>
        </Alert>
      ) : null}

      {ambiguous.map((line) => (
        <AsyncSelect
          key={line.orderLineId}
          label={`${line.articleCode ?? line.name} — ${line.quantity}`}
          value={choices[line.orderLineId] ?? ''}
          onChange={(value) => onChange(line.orderLineId, value)}
          options={line.locations.map((location) => ({
            value: location.id,
            label:
              location.zoneCode === null || location.zoneCode === ''
                ? (location.locationCode ?? location.id)
                : `${location.zoneCode} · ${location.locationCode}`,
            hint: t('orders.stockPlan.available', { quantity: location.availableQuantity }),
          }))}
          required
        />
      ))}
    </div>
  )
}

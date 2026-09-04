import { Check, Minus } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { CUSTOMER_CAPABILITIES, type Customer } from '../types/customer'
import { Badge } from '@/shared/components/ui/badge'
import { cn } from '@/shared/utils/cn'

interface CustomerCapabilitiesProps {
  customer: Customer
  /** `compact` pour une cellule de table, `detailed` pour la fiche. */
  variant?: 'compact' | 'detailed'
}

/**
 * Capacités activées d'un client.
 *
 * Les cinq viennent de l'API — `catalogEnabled`, `stockEnabled`,
 * `packageEnabled`, `appointmentEnabled`, `trackingEnabled`. Le §18 interdit
 * d'en inventer une, et il n'y en a pas d'autre à afficher.
 *
 * En liste, seules les capacités actives sont montrées : une cellule chargée de
 * cinq pastilles dont trois grisées se lit moins vite qu'une cellule qui ne
 * porte que ce qui est vrai.
 */
export function CustomerCapabilities({
  customer,
  variant = 'detailed',
}: CustomerCapabilitiesProps) {
  const { t } = useTranslation()

  if (variant === 'compact') {
    const enabled = CUSTOMER_CAPABILITIES.filter((capability) => customer[capability])

    if (enabled.length === 0) {
      return <span className="text-muted-foreground">—</span>
    }

    return (
      <div className="flex flex-wrap gap-1">
        {enabled.map((capability) => (
          <Badge key={capability} variant="secondary" className="font-normal">
            {t(`customers.capability.${capability}`)}
          </Badge>
        ))}
      </div>
    )
  }

  return (
    <dl className="divide-y">
      {CUSTOMER_CAPABILITIES.map((capability) => {
        const active = customer[capability]

        return (
          <div key={capability} className="flex items-center justify-between py-2.5">
            <dt className="text-sm">{t(`customers.capability.${capability}`)}</dt>
            <dd
              className={cn(
                'flex items-center gap-1.5 text-sm font-medium',
                active ? 'text-success' : 'text-muted-foreground',
              )}
            >
              {active ? (
                <Check className="size-4" aria-hidden />
              ) : (
                <Minus className="size-4" aria-hidden />
              )}
              {active ? t('common.enabled') : t('common.disabled')}
            </dd>
          </div>
        )
      })}
    </dl>
  )
}

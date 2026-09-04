import { MapPin, Package } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { SearchInput } from '@/shared/components/data/SearchInput'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import type { PoolOrder } from '../../types/pool'

interface PoolMapPanelProps {
  orders: PoolOrder[]
  search: string
  onSearchChange: (search: string) => void
  /** Amène la commande au centre de la carte. */
  onFocus: (order: PoolOrder) => void
  /** Absent tant qu'aucune tournée brouillon n'est choisie. */
  onPlan?: (orderId: string) => void
  isPending: boolean
}

/**
 * Ce qui attend une tournée, à côté de la carte.
 *
 * La recherche est là parce qu'un mois de commandes ne se parcourt pas à la
 * molette : on cherche un client ou un numéro, et la carte suit.
 *
 * Cliquer une commande la centre plutôt que de la planifier. Ce sont deux
 * intentions différentes — regarder où elle est, et décider qu'elle part — et
 * les confondre ferait planifier par mégarde.
 *
 * Une commande sans coordonnées reste listée : elle est planifiable, seulement
 * pas plaçable. Le bouton qui la centrerait est alors inerte.
 */
export function PoolMapPanel({
  orders,
  search,
  onSearchChange,
  onFocus,
  onPlan,
  isPending,
}: PoolMapPanelProps) {
  const { t } = useTranslation()

  const locatable = (order: PoolOrder): boolean =>
    order.services.some((service) => service.latitude !== null && service.longitude !== null)

  return (
    <div className="flex h-full min-h-0 flex-col gap-2">
      <SearchInput value={search} onChange={onSearchChange} label={t('planning.searchPool')} />

      {orders.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('planning.poolEmpty')}</p>
      ) : (
        <ul className="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto pr-1">
          {orders.map((order) => (
            <li key={order.id} className="rounded-lg border bg-card p-2">
              <button
                type="button"
                disabled={!locatable(order)}
                onClick={() => onFocus(order)}
                title={locatable(order) ? t('planning.showOnMap') : t('planning.notPlaceable')}
                className="w-full text-left disabled:cursor-default"
              >
                <span className="block truncate font-medium">{order.orderNumber}</span>
                <span className="block truncate text-xs text-muted-foreground">
                  {order.customerName ?? order.customerId}
                </span>
              </button>

              <div className="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
                <Badge variant="outline">
                  {t('planning.serviceCount', { count: order.serviceCount })}
                </Badge>
                <span className="flex items-center gap-1">
                  <MapPin className="size-3" aria-hidden />
                  {t('planning.addressCount', { count: order.addressCount })}
                </span>
                <span className="flex items-center gap-1">
                  <Package className="size-3" aria-hidden />
                  {order.totalPackages}
                </span>
                <span>{order.totalWeight} kg</span>
              </div>

              {onPlan === undefined ? null : (
                <Button
                  type="button"
                  size="sm"
                  className="mt-1.5 w-full"
                  disabled={isPending}
                  onClick={() => onPlan(order.id)}
                >
                  {t('planning.planOrder')}
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

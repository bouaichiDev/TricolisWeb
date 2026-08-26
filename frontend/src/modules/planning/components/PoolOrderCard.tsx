import { ChevronDown, ChevronRight, GripVertical, MapPin, Package } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { formatDate } from '@/shared/utils/format'

import { startPlanningDrag } from '../dnd'
import type { PoolOrder } from '../types/pool'

interface PoolOrderCardProps {
  order: PoolOrder
  /** `null` tant qu'aucune tournée n'est choisie : rien à planifier vers nulle part. */
  onPlanOrder: (() => void) | null
  onPlanService: ((orderServiceId: string) => void) | null
  isPending: boolean
  /**
   * Rend la carte et ses services glissables vers une tournée.
   *
   * Faux par défaut : l'écran de planification range ses tournées à côté du
   * pool et se pilote au clic. Le glisser n'a de sens que là où des colonnes
   * peuvent le recevoir.
   */
  draggable?: boolean
}

/**
 * Une commande du pool, dépliable sur ses services.
 *
 * **Planifier la commande prend tous ses services éligibles**, sans demander
 * lesquels : le §40 l'exige, et le serveur applique de toute façon la même
 * règle. Déplier permet d'en prendre un seul quand on ne veut pas de tout.
 *
 * Les chiffres montrés sont ceux du reste à planifier, pas de la commande
 * entière — c'est ce qui entrera dans la tournée.
 */
export function PoolOrderCard({
  order,
  onPlanOrder,
  onPlanService,
  isPending,
  draggable = false,
}: PoolOrderCardProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  return (
    <li
      className={`flex flex-col gap-2 rounded-lg border bg-card p-3 ${
        draggable ? 'cursor-grab active:cursor-grabbing' : ''
      }`}
      draggable={draggable}
      onDragStart={
        draggable
          ? (event) =>
              startPlanningDrag(event, {
                kind: 'order',
                id: order.id,
                label: order.orderNumber,
              })
          : undefined
      }
    >
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="flex items-center gap-2 font-medium">
            {draggable ? (
              <GripVertical className="size-4 shrink-0 text-muted-foreground" aria-hidden />
            ) : null}
            <button
              type="button"
              onClick={() => setOpen((current) => !current)}
              aria-expanded={open}
              aria-label={t(open ? 'planning.collapse' : 'planning.expand')}
              className="text-muted-foreground"
            >
              {open ? (
                <ChevronDown className="size-4" aria-hidden />
              ) : (
                <ChevronRight className="size-4" aria-hidden />
              )}
            </button>
            {order.orderNumber}
          </p>
          <p className="truncate pl-6 text-sm text-muted-foreground">
            {order.customerName ?? order.customerId}
          </p>
        </div>

        {onPlanOrder === null ? null : (
          <Button type="button" size="sm" onClick={onPlanOrder} disabled={isPending}>
            {t('planning.planOrder')}
          </Button>
        )}
      </div>

      <div className="flex flex-wrap items-center gap-2 pl-6 text-xs text-muted-foreground">
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
        {order.earliestRequestedDate === null ? null : (
          <span>{formatDate(order.earliestRequestedDate)}</span>
        )}
      </div>

      {open ? (
        <ul className="flex flex-col gap-1 pl-6">
          {order.services.map((service) => (
            <li
              key={service.id}
              className={`flex flex-wrap items-center justify-between gap-2 rounded-md border px-2 py-1.5 ${
                draggable ? 'cursor-grab active:cursor-grabbing' : ''
              }`}
              draggable={draggable}
              // Le glisser d'un service ne doit pas remonter à la carte, qui
              // emporterait la commande entière.
              onDragStart={
                draggable
                  ? (event) => {
                      event.stopPropagation()
                      startPlanningDrag(event, {
                        kind: 'service',
                        id: service.id,
                        label: service.serviceName ?? service.serviceNumber,
                      })
                    }
                  : undefined
              }
            >
              <span className="min-w-0">
                <span className="block truncate text-sm">
                  {service.serviceName ?? service.serviceNumber}
                </span>
                <span className="block truncate text-xs text-muted-foreground">
                  {service.addressLabel ?? t('planning.noAddress')}
                </span>
              </span>

              {onPlanService === null ? null : (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={() => onPlanService(service.id)}
                  disabled={isPending}
                >
                  {t('planning.planService')}
                </Button>
              )}
            </li>
          ))}
        </ul>
      ) : null}
    </li>
  )
}

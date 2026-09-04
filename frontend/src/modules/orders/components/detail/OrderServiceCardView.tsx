import { ArrowRight, ArrowRightLeft, History, MapPin, Pencil } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Button } from '@/shared/components/ui/button'
import { formatDate } from '@/shared/utils/format'

import { addressHint, addressLabel } from '../../hooks/useServiceScope'
import type { OrderService } from '../../types/orderDetail'

const show = (value: number | string | null | undefined): string =>
  value === null || value === undefined ? '—' : String(value)

interface OrderServiceCardViewProps {
  service: OrderService
  position: number
  /** Faux quand le statut de la commande ferme son contenu : pas de crayon. */
  editable: boolean
  onOpen: () => void
  onEdit: () => void
  onHistory: () => void
  onChangeStatus: () => void
}

/**
 * Un service, en vignette.
 *
 * L'adresse d'abord : c'est elle qui distingue deux services, un chargement et
 * une livraison portant souvent le même nom. Puis trois mesures — créneau,
 * durée, total client — et le contact : de quoi décider si c'est bien ce
 * service qu'on cherchait. Le reste s'ouvre dans le panneau latéral, où les
 * quatorze champs tiennent sans écraser la grille.
 *
 * Les actions sont des icônes, comme dans les tableaux de lignes et de colis :
 * leurs libellés viennent au survol par `title`, et au lecteur d'écran par
 * `aria-label`. Le panneau latéral, lui, les nomme en toutes lettres — c'est là
 * qu'on apprend ce qu'elles font.
 */
export function OrderServiceCardView({
  service,
  position,
  editable,
  onOpen,
  onEdit,
  onHistory,
  onChangeStatus,
}: OrderServiceCardViewProps) {
  const { t } = useTranslation()

  const from = service.operational.requestedFrom ?? service.operational.requestedDate
  const to = service.operational.requestedTo ?? service.operational.requestedDate
  const window =
    from === null ? '—' : from === to ? formatDate(from) : `${formatDate(from)} → ${formatDate(to)}`

  const contact = (service.contacts ?? []).find((item) => item.isPrimary) ?? service.contacts?.[0]
  const contactLabel =
    contact === undefined
      ? t('orders.services.noContact')
      : [
          `${contact.firstName ?? ''} ${contact.lastName ?? ''}`.trim(),
          contact.mobile ?? contact.phone ?? contact.email,
        ]
          .filter(Boolean)
          .join(' · ')

  const measures = [
    { labelKey: 'orders.services.timing', value: window },
    {
      labelKey: 'orders.fields.requiredTimeMinutes',
      value: `${show(service.operational.requiredTimeMinutes)} min`,
    },
    { labelKey: 'orders.fields.customerTotalPrice', value: show(service.billing.customerTotalPrice) },
  ]

  return (
    <div className="flex flex-col gap-3 rounded-lg border bg-card p-4">
      <div className="flex items-start justify-between gap-2.5">
        <div className="min-w-0">
          <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            {t('orders.services.position', { position })}
          </p>
          <p className="truncate text-lg font-semibold">
            {service.service?.name ?? service.serviceNumber}
          </p>
          <p className="font-mono text-sm text-muted-foreground">{service.serviceNumber}</p>
        </div>
        <div className="flex shrink-0 items-center gap-1">
          <StatusBadge status={service.status} />

          <PermissionGuard permission="order_services.change_status">
            <Button
              type="button"
              variant="ghost"
              size="icon"
              onClick={onChangeStatus}
              title={t('orders.services.changeStatus')}
              aria-label={t('orders.services.changeStatus')}
            >
              <ArrowRightLeft className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
        </div>
      </div>

      <p className="flex items-start gap-1.5 border-t pt-2.5 text-sm">
        <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden />
        <span className="min-w-0">
          {service.address === undefined ? (
            <span className="text-muted-foreground">{t('orders.services.noAddress')}</span>
          ) : (
            <>
              <span className="block truncate">{addressLabel(service.address)}</span>
              {addressHint(service.address) === undefined ? null : (
                <span className="block truncate text-xs text-muted-foreground">
                  {addressHint(service.address)}
                </span>
              )}
            </>
          )}
        </span>
      </p>

      <dl className="grid grid-cols-3 gap-2.5 border-t pt-2.5">
        {measures.map((measure) => (
          <div key={measure.labelKey} className="min-w-0">
            <dt className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
              {t(measure.labelKey)}
            </dt>
            <dd className="truncate text-sm">{measure.value}</dd>
          </div>
        ))}
      </dl>

      <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-2.5">
        <span className="min-w-0 flex-1 truncate text-sm text-muted-foreground">
          {contactLabel}
        </span>

        <div className="flex shrink-0 items-center gap-1">
          {editable ? (
            <PermissionGuard permission="order_services.update">
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={onEdit}
                title={t('orders.services.edit')}
                aria-label={t('orders.services.edit')}
              >
                <Pencil className="size-4" aria-hidden />
              </Button>
            </PermissionGuard>
          ) : null}

          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={onHistory}
            title={t('orders.entityHistory.title')}
            aria-label={t('orders.entityHistory.title')}
          >
            <History className="size-4" aria-hidden />
          </Button>

          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={onOpen}
            title={t('orders.services.openDetail')}
            aria-label={t('orders.services.openDetail')}
          >
            <ArrowRight className="size-4" aria-hidden />
          </Button>
        </div>
      </div>
    </div>
  )
}

import { ArrowRightLeft, History, Pencil, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Button } from '@/shared/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/shared/components/ui/sheet'

import { addressLabel } from '../../hooks/useServiceScope'
import type { OrderPackage, OrderService } from '../../types/orderDetail'
import { FieldGrid } from './FieldGrid'
import { serviceFields } from './serviceFields'
import { ServicePackagesEditor } from './ServicePackagesEditor'

interface OrderServicePanelProps {
  orderId: string
  service: OrderService | null
  /** Colis de la commande, proposés à la prise en charge. */
  packages: OrderPackage[]
  editable: boolean
  onClose: () => void
  onEdit: () => void
  onDelete: () => void
  onChangeStatus: () => void
  onHistory: () => void
}

/**
 * Fiche complète d'un service, en panneau latéral.
 *
 * C'est le service qui porte l'adresse, le créneau, les montants, les contacts
 * et les colis pris en charge — il n'existe pas d'entité « arrêt ». Les
 * quatorze champs tiennent ici sans écraser la grille de vignettes.
 */
export function OrderServicePanel({
  orderId,
  service,
  packages,
  editable,
  onClose,
  onEdit,
  onDelete,
  onChangeStatus,
  onHistory,
}: OrderServicePanelProps) {
  const { t } = useTranslation()

  const fields = serviceFields(service)

  return (
    <Sheet open={service !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
        <SheetHeader>
          <SheetTitle className="flex flex-wrap items-center gap-2">
            {service?.service?.name ?? service?.serviceNumber}
            <StatusBadge status={service?.status} />
          </SheetTitle>
          <SheetDescription>
            {service?.address ? addressLabel(service.address) : t('orders.services.address')}
          </SheetDescription>
        </SheetHeader>

        {service ? (
          <div className="flex flex-col gap-4 px-4 pb-6">
            <FieldGrid items={fields} columns={2} />

            {service.operational.instructions !== null ? (
              <p className="border-t pt-4 text-sm text-muted-foreground">
                {service.operational.instructions}
              </p>
            ) : null}

            <div className="border-t pt-4">
              <p className="mb-2 text-sm font-medium">{t('orders.services.contacts')}</p>
              {(service.contacts ?? []).length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('orders.services.noContact')}</p>
              ) : (
                <ul className="flex flex-col gap-2">
                  {(service.contacts ?? []).map((contact) => (
                    <li key={contact.id} className="flex items-center gap-2.5">
                      <span className="grid size-9 shrink-0 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                        {`${contact.firstName?.[0] ?? ''}${contact.lastName?.[0] ?? ''}`.toUpperCase() ||
                          '?'}
                      </span>
                      <div className="min-w-0 flex-1">
                        <p className="truncate font-semibold">
                          {`${contact.firstName ?? ''} ${contact.lastName ?? ''}`.trim() || '—'}
                        </p>
                        <p className="text-sm text-muted-foreground">
                          {contact.isPrimary
                            ? t('orders.services.isPrimary')
                            : t(`contactRoles.${contact.contactRole}`, contact.contactRole ?? '')}
                        </p>
                      </div>
                      <span className="font-mono text-sm text-muted-foreground">
                        {contact.mobile ?? contact.phone ?? contact.email ?? '—'}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            <div className="border-t pt-4">
              <ServicePackagesEditor
                orderId={orderId}
                serviceId={service.id}
                packages={packages}
                editable={editable}
              />
            </div>

            <div className="flex flex-wrap gap-2 border-t pt-4">
              {editable ? (
                <PermissionGuard permission="order_services.update">
                  <Button type="button" onClick={onEdit}>
                    <Pencil className="size-4" aria-hidden />
                    {t('orders.services.edit')}
                  </Button>
                </PermissionGuard>
              ) : null}

              <PermissionGuard permission="order_services.change_status">
                <Button type="button" variant="outline" onClick={onChangeStatus}>
                  <ArrowRightLeft className="size-4" aria-hidden />
                  {t('orders.services.changeStatus')}
                </Button>
              </PermissionGuard>

              <Button type="button" variant="outline" onClick={onHistory}>
                <History className="size-4" aria-hidden />
                {t('orders.entityHistory.title')}
              </Button>

              {editable ? (
                <PermissionGuard permission="order_services.delete">
                  <Button type="button" variant="ghost" onClick={onDelete}>
                    <Trash2 className="size-4" aria-hidden />
                    {t('orders.services.remove')}
                  </Button>
                </PermissionGuard>
              ) : null}
            </div>
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  )
}

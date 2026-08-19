import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { Disclosure } from '@/shared/components/layout/Disclosure'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import { useDeleteOrderLine } from '../../hooks/useOrderContent'
import type { OrderLine } from '../../types/orderDetail'
import { EntityHistory } from './EntityHistory'
import { OrderLineDialog } from './OrderLineDialog'
import { OrderLineFields } from './OrderLineFields'
import { SummaryRow } from './SummaryRow'

interface OrderLinesTabProps {
  orderId: string
  lines: OrderLine[]
  /** Le contenu est-il encore modifiable ? Le backend en décide, pas l'écran. */
  editable: boolean
}

/**
 * Lignes de la commande.
 *
 * Cinq valeurs en clair — code-barres, quantité, poids, volume, statut — et le
 * reste sous le repli. Une ligne porte une vingtaine de champs ; les afficher
 * tous noyait les trois qu'on lit vraiment.
 *
 * `fromCatalog` est calculé par la ressource : il dit si la ligne provient d'un
 * article de catalogue ou d'une saisie libre. Les deux coexistent dans une même
 * commande.
 *
 * Les actions disparaissent quand la commande n'accepte plus de changement de
 * contenu : au-delà de `CONFIRMED`, lignes, colis et services sont engagés
 * auprès de l'exploitation.
 */
export function OrderLinesTab({ orderId, lines, editable }: OrderLinesTabProps) {
  const { t } = useTranslation()
  const [editing, setEditing] = useState<OrderLine | null>(null)
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<OrderLine | null>(null)
  const remove = useDeleteOrderLine(orderId)

  return (
    <SectionCard
      title={t('orders.lines.title')}
      description={t('orders.lines.description')}
      actions={
        editable ? (
          <PermissionGuard permission="order_lines.create">
            <Button type="button" variant="outline" size="sm" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('orders.lines.add')}
            </Button>
          </PermissionGuard>
        ) : null
      }
    >
      {lines.length === 0 ? (
        <EmptyState title={t('orders.lines.title')} />
      ) : (
        <ul className="flex flex-col gap-3">
          {lines.map((line) => (
            <li key={line.id} className="rounded-md border p-3">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <p className="flex min-w-0 flex-wrap items-center gap-2 font-medium">
                  {line.name}
                  {line.fromCatalog ? (
                    <Badge variant="secondary">{t('orders.lines.catalogItem')}</Badge>
                  ) : (
                    <Badge variant="outline">{t('orders.lines.manualEntry')}</Badge>
                  )}
                </p>

                {editable ? (
                  <div className="flex gap-1">
                    <PermissionGuard permission="order_lines.update">
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setEditing(line)}
                        aria-label={t('orders.lines.edit')}
                      >
                        <Pencil className="size-4" aria-hidden />
                      </Button>
                    </PermissionGuard>

                    <PermissionGuard permission="order_lines.delete">
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setDeleting(line)}
                        aria-label={t('orders.lines.remove')}
                      >
                        <Trash2 className="size-4" aria-hidden />
                      </Button>
                    </PermissionGuard>
                  </div>
                ) : null}
              </div>

              <div className="mt-2">
                <SummaryRow
                  items={[
                    { labelKey: 'orders.fields.barcode', value: line.barcode },
                    { labelKey: 'orders.fields.quantity', value: line.quantity },
                    { labelKey: 'orders.fields.weight', value: line.weight },
                    { labelKey: 'orders.fields.volume', value: line.volume },
                    { labelKey: 'orders.fields.status', value: line.status },
                  ]}
                />
              </div>

              <div className="mt-2 border-t pt-2">
                <Disclosure>
                  <OrderLineFields line={line} />
                  <p className="mt-2 text-xs text-muted-foreground">
                    {t('orders.lines.trackedHint')}
                  </p>
                </Disclosure>

                <EntityHistory entityType="order_line" entityId={line.id} />
              </div>
            </li>
          ))}
        </ul>
      )}

      <OrderLineDialog
        key={editing?.id ?? 'new'}
        orderId={orderId}
        line={editing}
        open={editing !== null || creating}
        onOpenChange={(open) => {
          if (!open) {
            setEditing(null)
            setCreating(false)
          }
        }}
      />

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('orders.lines.remove')}
        description={t('orders.lines.deleteConfirm')}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (deleting === null) return
          remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })
        }}
      />
    </SectionCard>
  )
}

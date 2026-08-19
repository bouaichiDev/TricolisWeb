import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import { useDeleteOrderLine } from '../../hooks/useOrderContent'
import type { OrderLine } from '../../types/orderDetail'
import { EntityHistory } from './EntityHistory'
import { OrderLineDialog } from './OrderLineDialog'

const show = (value: number | string | null): string => (value === null ? '—' : String(value))

interface OrderLinesTabProps {
  orderId: string
  lines: OrderLine[]
  /** Le contenu est-il encore modifiable ? Le backend en décide, pas l'écran. */
  editable: boolean
}

/**
 * Lignes de la commande, avec leur historique et leur modification.
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
                <div className="min-w-0">
                  <p className="flex flex-wrap items-center gap-2 font-medium">
                    {line.name}
                    {line.fromCatalog ? (
                      <Badge variant="secondary">{t('orders.lines.catalogItem')}</Badge>
                    ) : (
                      <Badge variant="outline">{t('orders.lines.manualEntry')}</Badge>
                    )}
                  </p>
                  <p className="mt-1 text-xs text-muted-foreground">
                    {[line.articleCode, line.barcode].filter(Boolean).join(' · ') || '—'}
                  </p>
                  <p className="mt-1 text-sm">
                    {t('orders.fields.quantity')} {show(line.quantity)} ·{' '}
                    {t('orders.fields.weight')} {show(line.weight)} ·{' '}
                    {t('orders.fields.volume')} {show(line.volume)}
                  </p>
                </div>

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

              <div className="mt-2 border-t pt-2">
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

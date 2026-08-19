import { CornerDownRight, Pencil, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Disclosure } from '@/shared/components/layout/Disclosure'
import { Button } from '@/shared/components/ui/button'

import type { LineUsage } from '../../schemas/orderAllocations'
import type { OrderLine, OrderPackage, PackageTreeNode } from '../../types/orderDetail'
import { EntityHistory } from './EntityHistory'
import { OrderPackageFields } from './OrderPackageFields'
import { PackageLinesEditor } from './PackageLinesEditor'
import { SummaryRow } from './SummaryRow'

interface PackageNodeCardProps {
  orderId: string
  node: PackageTreeNode
  /** Profondeur dans l'arbre, rendue par un décalage. */
  depth: number
  detail?: OrderPackage
  parentLabel?: string
  lines: OrderLine[]
  usage: Map<string, LineUsage>
  editable: boolean
  onEdit: (pkg: OrderPackage) => void
  onDelete: (pkg: OrderPackage) => void
}

/**
 * Un colis de l'arbre.
 *
 * Six valeurs en clair — code-barres, type, quantité, poids, volume, nombre de
 * lignes — et le reste sous le repli, contenu du colis compris. C'est là que se
 * gère la relation `PackageOrderLine` : quelles lignes ce colis transporte, et
 * en quelle quantité.
 */
export function PackageNodeCard({
  orderId,
  node,
  depth,
  detail,
  parentLabel,
  lines,
  usage,
  editable,
  onEdit,
  onDelete,
}: PackageNodeCardProps) {
  const { t } = useTranslation()

  return (
    <li style={{ marginLeft: `${depth * 1.5}rem` }} className="rounded-md border p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="flex items-center gap-2 font-medium">
          {depth > 0 ? (
            <CornerDownRight className="size-4 text-muted-foreground" aria-hidden />
          ) : null}
          {node.reference ?? node.barcode ?? node.id}
        </span>

        <div className="flex items-center gap-1">
          <StatusBadge status={node.status} />

          {editable && detail ? (
            <>
              <PermissionGuard permission="packages.update">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => onEdit(detail)}
                  aria-label={t('orders.packages.edit')}
                >
                  <Pencil className="size-4" aria-hidden />
                </Button>
              </PermissionGuard>

              <PermissionGuard permission="packages.delete">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => onDelete(detail)}
                  aria-label={t('orders.packages.remove')}
                >
                  <Trash2 className="size-4" aria-hidden />
                </Button>
              </PermissionGuard>
            </>
          ) : null}
        </div>
      </div>

      <div className="mt-2">
        <SummaryRow
          items={[
            { labelKey: 'orders.fields.barcode', value: detail?.barcode ?? node.barcode },
            { labelKey: 'orders.packages.packageType', value: detail?.packageType?.name },
            { labelKey: 'orders.fields.quantity', value: detail?.quantity ?? node.quantity },
            // La ressource complète prime sur le nœud de l'arbre, qui n'en est
            // qu'une projection allégée.
            { labelKey: 'orders.fields.weight', value: detail?.weight ?? node.weight },
            { labelKey: 'orders.fields.volume', value: detail?.volume ?? node.volume },
            {
              labelKey: 'orders.lines.title',
              value: t('orders.lineCount', { count: detail?.lines?.length ?? 0 }),
            },
          ]}
        />
      </div>

      {detail ? (
        <div className="mt-2 border-t pt-2">
          <Disclosure>
            <OrderPackageFields pkg={detail} parentLabel={parentLabel} />

            <div className="mt-4 border-t pt-4">
              <PackageLinesEditor
                orderId={orderId}
                pkg={detail}
                lines={lines}
                usage={usage}
                editable={editable}
              />
            </div>
          </Disclosure>

          <EntityHistory entityType="package" entityId={node.id} />
        </div>
      ) : null}
    </li>
  )
}

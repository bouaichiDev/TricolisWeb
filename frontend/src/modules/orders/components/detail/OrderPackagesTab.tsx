import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable } from '@/shared/components/data/DataTable'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { Button } from '@/shared/components/ui/button'

import { useDeleteOrderPackage, useUpdateOrderPackage } from '../../hooks/useOrderContent'
import { usePackageTree } from '../../hooks/useOrders'
import { orderLineUsage } from '../../schemas/orderAllocations'
import type { OrderLine, OrderPackage, PackageTreeNode } from '../../types/orderDetail'
import { ChangeEntityStatusDialog } from './ChangeEntityStatusDialog'
import { OrderPackageDialog } from './OrderPackageDialog'
import { packageColumns, type FlatNode } from './packageColumns'
import { PackageContentSheet } from './PackageContentSheet'
import { StatusTimelineSheet } from './StatusTimelineSheet'
import { packageDisplayName } from './packageParents'
import { TableToolbar } from './TableToolbar'

/** Aplatit l'arbre en conservant la profondeur, pour l'indentation du tableau. */
function flatten(nodes: PackageTreeNode[], depth = 0): FlatNode[] {
  return nodes.flatMap((node) => [{ node, depth }, ...flatten(node.children, depth + 1)])
}

interface OrderPackagesTabProps {
  orderId: string
  packages: OrderPackage[]
  lines: OrderLine[]
  editable: boolean
}

/**
 * Colis de la commande, en tableau.
 *
 * L'arbre vient de `GET /orders/{order}/packages/tree` : c'est le backend qui
 * l'assemble. L'imbrication est rendue par un décalage dans la première
 * colonne — un tableau plat perdrait la hiérarchie que le modèle porte.
 *
 * Le contenu du colis — la relation `PackageOrderLine` — s'ouvre en tiroir : il
 * demande trois nombres par ligne, ce qu'aucune colonne ne peut porter.
 */
export function OrderPackagesTab({ orderId, packages, lines, editable }: OrderPackagesTabProps) {
  const { t } = useTranslation()
  const tree = usePackageTree(orderId)
  const remove = useDeleteOrderPackage(orderId)

  const [editing, setEditing] = useState<OrderPackage | null>(null)
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<OrderPackage | null>(null)
  const [content, setContent] = useState<OrderPackage | null>(null)
  const [history, setHistory] = useState<OrderPackage | null>(null)
  const [changingStatus, setChangingStatus] = useState<OrderPackage | null>(null)
  const update = useUpdateOrderPackage(orderId)

  const byId = new Map(packages.map((item) => [item.id, item]))
  const usage = orderLineUsage(lines, packages)

  if (tree.isPending) return <ListSkeleton />
  if (tree.error) return <ErrorState error={tree.error} onRetry={() => void tree.refetch()} />

  const rows = flatten(tree.data ?? [])

  const columns = packageColumns(t, {
    byId,
    editable,
    onContent: setContent,
    onHistory: setHistory,
    onStatus: setChangingStatus,
    onEdit: setEditing,
    onDelete: setDeleting,
  })

  return (
    <div className="overflow-hidden rounded-lg border bg-card">
      <TableToolbar
        title={t('orders.packages.title')}
        description={t('orders.wizard.packagesOptional')}
      >
        {editable ? (
          <PermissionGuard permission="packages.create">
            <Button type="button" variant="outline" size="sm" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('orders.packages.add')}
            </Button>
          </PermissionGuard>
        ) : null}
      </TableToolbar>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={({ node }) => node.id}
        emptyMessage={t('orders.packages.empty')}
      />

      {editing !== null || creating ? (
        <OrderPackageDialog
          key={editing?.id ?? 'new'}
          orderId={orderId}
          pkg={editing}
          packages={packages}
          open
          onOpenChange={(open) => {
            if (!open) {
              setEditing(null)
              setCreating(false)
            }
          }}
        />
      ) : null}

      <PackageContentSheet
        orderId={orderId}
        pkg={content}
        parentLabel={
          content?.parentPackageId
            ? packageDisplayName(byId.get(content.parentPackageId) ?? content)
            : undefined
        }
        lines={lines}
        usage={usage}
        editable={editable}
        onClose={() => setContent(null)}
      />

      <ChangeEntityStatusDialog
        source="package"
        entityId={changingStatus?.id ?? null}
        title={changingStatus ? packageDisplayName(changingStatus) : undefined}
        currentStatus={changingStatus?.status}
        isPending={update.isPending}
        onSubmit={(status, onError) =>
          update.mutate(
            { id: changingStatus?.id ?? '', status },
            { onSuccess: () => setChangingStatus(null), onError },
          )
        }
        onClose={() => setChangingStatus(null)}
      />

      <StatusTimelineSheet
        entityType="package"
        entityId={history?.id ?? null}
        title={history ? packageDisplayName(history) : undefined}
        subtitle={history?.packageType?.name}
        currentStatus={history?.status}
        onClose={() => setHistory(null)}
      />

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('orders.packages.remove')}
        description={t('orders.packages.deleteConfirm')}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (deleting === null) return
          remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })
        }}
      />
    </div>
  )
}

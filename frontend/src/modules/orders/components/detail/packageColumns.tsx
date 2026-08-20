import { ArrowRightLeft, Boxes, CornerDownRight, History, Pencil, Trash2 } from 'lucide-react'
import type { TFunction } from 'i18next'

import type { Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'

import type { OrderPackage, PackageTreeNode } from '../../types/orderDetail'
import { packageDimensions } from './packageDimensions'
import { RowActions } from './RowActions'

export interface FlatNode {
  node: PackageTreeNode
  depth: number
}

interface Handlers {
  byId: Map<string, OrderPackage>
  editable: boolean
  onContent: (pkg: OrderPackage) => void
  onHistory: (pkg: OrderPackage) => void
  onStatus: (pkg: OrderPackage) => void
  onEdit: (pkg: OrderPackage) => void
  onDelete: (pkg: OrderPackage) => void
}

const right = (value: string) => <span className="block text-right font-mono">{value}</span>

/**
 * Colonnes du tableau des colis.
 *
 * L'imbrication est rendue par un décalage dans la première colonne : un
 * tableau plat perdrait la hiérarchie que le modèle porte. Les dimensions sont
 * réunies en une colonne — trois de plus auraient rendu la ligne illisible.
 *
 * L'arbre ne renvoie qu'une projection allégée du colis ; les valeurs complètes
 * viennent de `byId`, avec le nœud en repli.
 */
export function packageColumns(
  t: TFunction,
  { byId, editable, onContent, onHistory, onStatus, onEdit, onDelete }: Handlers,
): Column<FlatNode>[] {
  return [
    {
      key: 'reference',
      header: t('orders.packages.number'),
      cell: ({ node, depth }) => (
        <span
          className="flex items-center gap-2 font-medium"
          style={{ paddingLeft: `${depth * 1.25}rem` }}
        >
          {depth > 0 ? (
            <CornerDownRight className="size-4 shrink-0 text-muted-foreground" aria-hidden />
          ) : null}
          {node.reference ?? node.barcode ?? node.id}
        </span>
      ),
    },
    {
      key: 'type',
      header: t('orders.packages.packageType'),
      cell: ({ node }) => byId.get(node.id)?.packageType?.name ?? '—',
    },
    {
      key: 'weight',
      header: t('orders.fields.weight'),
      cell: ({ node }) => right(String(byId.get(node.id)?.weight ?? node.weight ?? '—')),
    },
    {
      key: 'volume',
      header: t('orders.fields.volume'),
      cell: ({ node }) => right(String(byId.get(node.id)?.volume ?? node.volume ?? '—')),
    },
    {
      key: 'dimensions',
      header: t('orders.packages.dimensions'),
      hideOnMobile: true,
      cell: ({ node }) => {
        const detail = byId.get(node.id)

        return <span className="font-mono">{(detail && packageDimensions(detail)) ?? '—'}</span>
      },
    },
    {
      key: 'status',
      header: t('orders.fields.status'),
      cell: ({ node }) => <StatusBadge status={node.status} />,
    },
    {
      key: 'actions',
      header: t('common.actions'),
      cell: ({ node }) => {
        const detail = byId.get(node.id)

        if (detail === undefined) return null

        return (
          <RowActions
            actions={[
              {
                key: 'content',
                label: t('orders.packages.contents'),
                icon: Boxes,
                onSelect: () => onContent(detail),
              },
              {
                key: 'history',
                label: t('orders.entityHistory.title'),
                icon: History,
                onSelect: () => onHistory(detail),
              },
              ...(editable
                ? [
                    {
                      key: 'status',
                      label: t('orders.packages.changeStatus'),
                      icon: ArrowRightLeft,
                      permission: 'packages.update',
                      onSelect: () => onStatus(detail),
                    },
                    {
                      key: 'edit',
                      label: t('orders.packages.edit'),
                      icon: Pencil,
                      permission: 'packages.update',
                      onSelect: () => onEdit(detail),
                    },
                    {
                      key: 'delete',
                      label: t('orders.packages.remove'),
                      icon: Trash2,
                      permission: 'packages.delete',
                      destructive: true,
                      onSelect: () => onDelete(detail),
                    },
                  ]
                : []),
            ]}
          />
        )
      },
    },
  ]
}

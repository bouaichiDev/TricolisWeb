import { ChevronDown, ChevronRight } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { cn } from '@/shared/utils/cn'

import type { StockLocationTreeNode as TreeNode } from '../types/stock'
import { STOCK_LOCATION_SOURCE } from '../utils/stockSources'

interface StockLocationTreeNodeProps {
  node: TreeNode
  depth: number
}

/**
 * Un emplacement dans l'arbre, et ses enfants.
 *
 * L'arbre arrive **entier** du serveur : `StockLocationListQuery::tree()` charge
 * tout puis regroupe en mémoire. Le repli n'évite donc aucune requête — il évite
 * de dérouler mille lignes d'un coup, ce qui est un problème différent mais
 * réel.
 *
 * Les racines sont ouvertes, les branches fermées : c'est le niveau où l'on
 * cherche d'abord une zone, pas une travée.
 */
export function StockLocationTreeNode({ node, depth }: StockLocationTreeNodeProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(depth === 0)

  const hasChildren = node.children.length > 0

  return (
    <li>
      <div
        className="flex items-center gap-2 rounded-md py-1.5 pr-2 hover:bg-muted/50"
        style={{ paddingLeft: `${depth * 1.25 + 0.25}rem` }}
      >
        {hasChildren ? (
          <button
            type="button"
            onClick={() => setOpen((current) => !current)}
            className="rounded p-0.5 text-muted-foreground hover:text-foreground"
            aria-expanded={open}
            aria-label={open ? t('common.collapse') : t('common.expand')}
          >
            {open ? (
              <ChevronDown className="size-4" aria-hidden />
            ) : (
              <ChevronRight className="size-4" aria-hidden />
            )}
          </button>
        ) : (
          <span className="size-5" aria-hidden />
        )}

        <Link
          to={`/stock/locations/${node.id}`}
          className={cn('truncate hover:underline', depth === 0 && 'font-medium')}
        >
          {node.locationCode}
        </Link>

        {node.zoneCode ? (
          <span className="truncate text-xs text-muted-foreground">{node.zoneCode}</span>
        ) : null}

        <StatusBadge status={node.status} source={STOCK_LOCATION_SOURCE} />

        {hasChildren ? (
          <span className="text-xs text-muted-foreground">
            {t('stock.childCount', { count: node.children.length })}
          </span>
        ) : null}
      </div>

      {hasChildren && open ? (
        <ul>
          {node.children.map((child) => (
            <StockLocationTreeNode key={child.id} node={child} depth={depth + 1} />
          ))}
        </ul>
      ) : null}
    </li>
  )
}

import { useTranslation } from 'react-i18next'

import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'

import { StockLocationTreeNode } from './StockLocationTreeNode'
import { useStockLocationTree } from '../hooks/useStockLocations'

interface StockLocationTreeProps {
  /** Sans dépôt, l'arbre du parc entier remonte : à n'employer qu'à la demande. */
  depotId: string
}

/**
 * Arbre des emplacements d'un dépôt.
 *
 * `GET /stock-locations/tree` n'est **pas paginé** : le serveur charge tous les
 * emplacements en une requête puis les regroupe par parent. C'est ce qui évite
 * le N+1 d'un arbre déplié niveau par niveau, mais cela veut aussi dire que la
 * réponse grandit avec le dépôt. D'où l'exigence d'un dépôt : demander l'arbre
 * du parc entier ne se justifie sur aucun écran.
 *
 * La vue liste reste la vue par défaut. Retrouver un code se fait par la
 * recherche paginée ; l'arbre sert à comprendre comment un dépôt est **rangé**,
 * ce qu'une liste plate ne montre pas.
 */
export function StockLocationTree({ depotId }: StockLocationTreeProps) {
  const { t } = useTranslation()
  const { data, isPending, error, refetch } = useStockLocationTree(depotId, depotId !== '')

  if (depotId === '') {
    return (
      <EmptyState title={t('stock.treeNeedsDepot')} description={t('stock.treeNeedsDepotHint')} />
    )
  }

  if (isPending) return <ListSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />

  const roots = data ?? []

  if (roots.length === 0) {
    return <EmptyState title={t('stock.noLocation')} description={t('stock.noLocationHint')} />
  }

  return (
    <div className="rounded-lg border bg-card p-2">
      <ul>
        {roots.map((node) => (
          <StockLocationTreeNode key={node.id} node={node} depth={0} />
        ))}
      </ul>
    </div>
  )
}

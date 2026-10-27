import { toast } from 'sonner'

import { errorMessage } from '@/shared/api/errors'

import type { CatalogItem } from '../types'
import { useSetCatalogItemActive } from './useCatalog'

/**
 * Désactiver (après confirmation) ou réactiver un article — même geste depuis
 * la liste et depuis la fiche.
 */
export function useCatalogItemToggle() {
  const setActive = useSetCatalogItemActive()

  const toggle = async (item: CatalogItem) => {
    if (item.isActive && !window.confirm('Désactiver cet article ? Il ne sera plus proposé dans vos nouvelles commandes ; les commandes et le stock existants ne changent pas.')) return
    try {
      await setActive.mutateAsync({ id: item.id, active: !item.isActive })
      toast.success(item.isActive ? 'Article désactivé.' : 'Article réactivé.')
    } catch (caught) {
      toast.error(errorMessage(caught))
    }
  }

  return { toggle, isPending: setActive.isPending }
}

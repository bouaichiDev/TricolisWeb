import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { menuApi, type MenuUpdateItem } from '../api/menu.api'

export const menuKeys = {
  all: ['menu'] as const,
  effective: () => [...menuKeys.all, 'effective'] as const,
  catalogue: () => [...menuKeys.all, 'catalogue'] as const,
}

/**
 * Menu de l'utilisateur connecté.
 *
 * Le cache est long : le menu ne change qu'au réglage de l'organisation, et le
 * recharger à chaque navigation ferait clignoter la barre latérale. Le réglage
 * l'invalide explicitement.
 */
export function useMenu() {
  return useQuery({
    queryKey: menuKeys.effective(),
    queryFn: menuApi.effective,
    staleTime: 10 * 60 * 1000,
  })
}

export function useMenuCatalogue() {
  return useQuery({
    queryKey: menuKeys.catalogue(),
    queryFn: menuApi.catalogue,
  })
}

export function useUpdateMenu() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (items: MenuUpdateItem[]) => menuApi.update(items),
    onSuccess: (items) => {
      queryClient.setQueryData(menuKeys.catalogue(), items)
      // La barre latérale doit refléter le réglage immédiatement : sans cette
      // invalidation, l'entrée masquée resterait affichée jusqu'au rechargement.
      void queryClient.invalidateQueries({ queryKey: menuKeys.effective() })
      toast.success(t('menu.saved'))
    },
  })
}

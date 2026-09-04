import { useQuery } from '@tanstack/react-query'

import { menuApi } from '../api/menu.api'

export const menuKeys = {
  all: ['menu'] as const,
  effective: () => [...menuKeys.all, 'effective'] as const,
}

/**
 * Menu de l'utilisateur connecté.
 *
 * Le cache est long : le menu ne change qu'au réglage d'un de ses rôles, et le
 * recharger à chaque navigation ferait clignoter la barre latérale. Le réglage
 * l'invalide explicitement — voir `useRoleMenu`.
 */
export function useMenu() {
  return useQuery({
    queryKey: menuKeys.effective(),
    queryFn: menuApi.effective,
    staleTime: 10 * 60 * 1000,
  })
}

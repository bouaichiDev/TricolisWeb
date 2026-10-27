import { useQuery } from '@tanstack/react-query'
import { Circle, IdCard, LayoutDashboard, Truck, UserCircle, type LucideIcon } from 'lucide-react'

import { useAuth } from '@/app/auth/useAuth'
import { api } from '@/shared/api/client'
import type { ApiEnvelope, NavigationEntry } from '@/shared/api/types'

export interface NavItem extends NavigationEntry {
  icon: LucideIcon
}

const ICONS: Record<string, LucideIcon> = {
  dashboard: LayoutDashboard,
  drivers: IdCard,
  vehicles: Truck,
  profile: UserCircle,
}

/**
 * Gestion des comptes et des rôles : jamais dans ce portail, elle reste au
 * back-office du transporteur. Le serveur ne la sert pas ; si une réponse la
 * contenait quand même, elle ne serait pas affichée.
 */
const NEVER = new Set(['users', 'roles'])

/**
 * Le menu du portail, tel que le serveur le compose selon les permissions.
 * Seules les adresses internes au portail sont retenues.
 */
export function useNavigation(): NavItem[] {
  const { isAuthenticated } = useAuth()
  const query = useQuery({
    queryKey: ['navigation'],
    queryFn: async () => (await api.get<ApiEnvelope<NavigationEntry[]>>('/navigation')).data,
    enabled: isAuthenticated,
    staleTime: 5 * 60 * 1000,
  })

  return (query.data ?? [])
    .filter((entry) => !NEVER.has(entry.key) && entry.path.startsWith('/') && !entry.path.startsWith('//'))
    .map((entry) => ({ ...entry, icon: ICONS[entry.key] ?? Circle }))
}

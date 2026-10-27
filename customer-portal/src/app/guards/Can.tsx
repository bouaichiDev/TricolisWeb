import type { ReactNode } from 'react'

import { usePermissions } from '@/app/auth/useAuth'

interface CanProps {
  /** Permission requise, ou liste dont toutes sont requises. */
  permission: string | readonly string[]
  children: ReactNode
  fallback?: ReactNode
}

/**
 * Masque un élément quand la permission manque.
 *
 * Confort, pas sécurité : le backend refuse l'appel de toute façon.
 */
export function Can({ permission, children, fallback = null }: CanProps) {
  const { canAll } = usePermissions()
  const required = typeof permission === 'string' ? [permission] : permission

  return <>{canAll(required) ? children : fallback}</>
}

import { useCallback, useMemo } from 'react'

import { useAuth } from './useAuth'

/**
 * Habilitations de l'organisation active.
 *
 * Le propriétaire d'une organisation contourne le contrôle — c'est exactement
 * ce que fait `BaseOrganizationPolicy` côté backend, et les deux doivent dire
 * la même chose, sinon l'interface masque un bouton que l'API accepterait.
 *
 * `isPlatformAdmin` est distinct d'une permission : une permission se délègue,
 * l'autorité plateforme non. Un administrateur d'organisme peut détenir
 * `organizations.view` sans pour autant administrer la plateforme, et c'est
 * exactement la confusion que cette distinction empêche.
 *
 * Ce que ce hook décide n'est **jamais** une sécurité : il ajuste l'affichage.
 * Le backend reste seul juge, et un appel non autorisé est refusé en 403 même
 * si l'interface l'a laissé passer.
 */
export function usePermissions() {
  const { permissions, isOwner, isPlatformAdmin } = useAuth()

  const granted = useMemo(() => new Set(permissions), [permissions])

  const has = useCallback(
    (permission: string) => isOwner || granted.has(permission),
    [granted, isOwner],
  )

  const hasAny = useCallback(
    (required: string[]) => isOwner || required.some((permission) => granted.has(permission)),
    [granted, isOwner],
  )

  const hasAll = useCallback(
    (required: string[]) => isOwner || required.every((permission) => granted.has(permission)),
    [granted, isOwner],
  )

  return { permissions, isOwner, isPlatformAdmin, has, hasAny, hasAll }
}

/** Raccourci pour un contrôle unique. */
export function usePermission(permission: string): boolean {
  const { has } = usePermissions()

  return has(permission)
}

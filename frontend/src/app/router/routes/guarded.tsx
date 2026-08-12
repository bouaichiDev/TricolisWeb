import type { ReactElement } from 'react'

import { ProtectedRoute } from '@/app/guards/ProtectedRoute'

interface GuardOptions {
  /**
   * Route réservée à l'administration de la plateforme.
   *
   * Distinct de la permission : `organizations.view` est légitime pour un
   * administrateur d'organisme, qui ne doit pourtant pas atteindre l'annuaire
   * global. La permission dit ce qu'on peut faire, la portée dit sur quoi.
   */
  platformOnly?: boolean
}

/**
 * Raccourci de déclaration de route.
 *
 * Chaque route métier porte sa permission à côté de sa page ; l'écrire en
 * toutes lettres à chaque fois noyait la table sous le JSX de garde.
 */
export function guarded(
  permission: string,
  element: ReactElement,
  options: GuardOptions = {},
): ReactElement {
  return (
    <ProtectedRoute permission={permission} platformOnly={options.platformOnly}>
      {element}
    </ProtectedRoute>
  )
}

import type { ReactElement } from 'react'

import { ProtectedRoute } from '@/app/guards/ProtectedRoute'

/**
 * Raccourci de declaration de route.
 *
 * Chaque route metier porte sa permission a cote de sa page ; l'ecrire en
 * toutes lettres a chaque fois noyait la table sous le JSX de garde.
 */
export function guarded(permission: string, element: ReactElement): ReactElement {
  return <ProtectedRoute permission={permission}>{element}</ProtectedRoute>
}

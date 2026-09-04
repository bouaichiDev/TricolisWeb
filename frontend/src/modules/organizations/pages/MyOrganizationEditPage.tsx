import { Navigate } from 'react-router-dom'

import { OrganizationEditPage } from './OrganizationEditPage'
import { useAuth } from '@/shared/hooks/useAuth'

/**
 * Modification de l'organisation active.
 *
 * Jumelle de `MyOrganizationPage` : l'identifiant vient de l'appartenance
 * résolue par `AuthProvider`, jamais de l'URL. Un identifiant saisi à la main
 * dans la barre d'adresse n'a donc aucune prise.
 *
 * Cette route existe parce que `/organizations/{id}/edit` est réservée à la
 * plateforme. Sans elle, « Modifier » menait un administrateur d'organisme vers
 * « Accès refusé » sur sa propre fiche — alors que l'API l'autorise.
 */
export function MyOrganizationEditPage() {
  const { organizationId, isLoading } = useAuth()

  if (isLoading) return null
  if (organizationId === null) return <Navigate to="/my-organization" replace />

  return <OrganizationEditPage organizationId={organizationId} />
}

import { useTranslation } from 'react-i18next'
import { Navigate } from 'react-router-dom'

import { OrganizationDetailPage } from './OrganizationDetailPage'
import { MenuSettingsPanel } from '@/modules/menu/components/MenuSettingsPanel'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useAuth } from '@/shared/hooks/useAuth'

/**
 * Fiche de l'organisation active.
 *
 * Aucun endpoint nouveau : la route réutilise `GET /organizations/{id}` avec
 * l'identifiant de l'appartenance courante. Elle existe pour donner un point
 * d'entrée stable à un administrateur d'organisme, sans lui présenter
 * l'annuaire global — lequel lui montrerait un périmètre qu'il n'a pas.
 *
 * L'organisation active n'est jamais choisie par l'URL : elle vient de
 * l'appartenance résolue par `AuthProvider`. Un identifiant saisi à la main
 * dans la barre d'adresse n'a donc aucune prise ici.
 */
export function MyOrganizationPage() {
  const { t } = useTranslation()
  const { organizationId, isLoading } = useAuth()

  if (isLoading) return null

  if (organizationId === null) {
    return <EmptyState title="Aucune organisation active" />
  }

  return (
    <div className="flex flex-col gap-6">
      <OrganizationDetailPage organizationId={organizationId} />

      {/* Régler le menu relève de l'administration de l'organisation, d'où la
          même permission que sa modification. */}
      <PermissionGuard permission="organizations.update">
        <SectionCard title={t('menu.settings')}>
          <MenuSettingsPanel />
        </SectionCard>
      </PermissionGuard>
    </div>
  )
}

/** Redirection depuis une ancienne adresse de liste, pour un compte local. */
export function RedirectToMyOrganization() {
  return <Navigate to="/my-organization" replace />
}

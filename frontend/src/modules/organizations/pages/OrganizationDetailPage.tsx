import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { OrganizationLogoPanel } from '../components/OrganizationLogoPanel'
import { useDeleteOrganization, useOrganization } from '../hooks/useOrganizations'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { usePermissions } from '@/shared/hooks/usePermission'
import { formatDateTime } from '@/shared/utils/format'

/**
 * Fiche d'une organisation.
 *
 * `organizationId` permet de réutiliser la page pour « Mon organisation », où
 * l'identifiant ne vient pas de l'URL mais de l'appartenance active — ce qui
 * empêche d'atteindre l'organisation d'un tiers en modifiant l'adresse.
 *
 * **Le lien de modification suit ce mode.** Il menait toujours à
 * `/organizations/{id}/edit`, une route réservée à la plateforme : un
 * administrateur d'organisme cliquait « Modifier » sur sa propre fiche et
 * tombait sur « Accès refusé ». L'API l'autorisait pourtant — c'est
 * `OrganizationPolicy::update`, qui accepte le propriétaire et le porteur
 * d'`organizations.update` sur sa propre organisation. Seul le chemin était
 * faux.
 */
export function OrganizationDetailPage({ organizationId }: { organizationId?: string } = {}) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const target = organizationId ?? id
  const { isPlatformAdmin } = usePermissions()
  const { data: organization, isPending, error, refetch } = useOrganization(target)
  const remove = useDeleteOrganization()

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!organization) return null

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={organization.name}
        subtitle={organization.code}
        status={organization.status}
        editTo={organizationId === undefined ? `/organizations/${target}/edit` : '/my-organization/edit'}
        editPermission="organizations.update"
        // La suppression relève de la plateforme. Sans rappel, `EntityHeader`
        // n'affiche pas le bouton : un administrateur d'organisme ne se voit
        // donc pas proposer une action que l'API refuserait — et qui, avant
        // cette correction, aurait réussi.
        onDelete={isPlatformAdmin ? () => setConfirmDelete(true) : undefined}
        deletePermission="organizations.delete"
      />

      <SectionCard title={t('organizations.sections.identity')}>
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('organizations.fields.legalName')}>
            {organization.legalName}
          </DetailField>
          <DetailField label={t('organizations.fields.registrationNumber')}>
            {organization.registrationNumber}
          </DetailField>
          <DetailField label={t('organizations.fields.taxNumber')}>
            {organization.taxNumber}
          </DetailField>
          <DetailField label={t('organizations.fields.email')}>{organization.email}</DetailField>
          <DetailField label={t('organizations.fields.phone')}>{organization.phone}</DetailField>
        </dl>
      </SectionCard>

      {/* Le logo se règle ici, et non plus sur la seule fiche « Mon
          organisation ». La plateforme administre les organismes — dont ceux
          qui n'ont pas encore d'administrateur local — et elle était la seule à
          ne pas pouvoir leur en poser un. Le panneau est le même des deux
          côtés : c'est `OrganizationPolicy::update` qui décide, et elle accepte
          l'administrateur plateforme comme le porteur d'`organizations.update`
          sur sa propre organisation. */}
      <PermissionGuard permission="organizations.update">
        <SectionCard title={t('organizations.logo.title')}>
          <OrganizationLogoPanel organizationId={target} hasLogo={organization.hasLogo} />
        </SectionCard>
      </PermissionGuard>

      <SectionCard title={t('organizations.sections.preferences')}>
        <dl className="grid gap-x-8 sm:grid-cols-3">
          <DetailField label={t('organizations.fields.preferredLanguage')}>
            {organization.preferredLanguage}
          </DetailField>
          <DetailField label={t('organizations.fields.timezone')}>
            {organization.timezone}
          </DetailField>
          <DetailField label={t('organizations.fields.currencyCode')}>
            {organization.currencyCode}
          </DetailField>
          <DetailField label={t('common.createdAt')}>
            {formatDateTime(organization.createdAt)}
          </DetailField>
          <DetailField label={t('common.updatedAt')}>
            {formatDateTime(organization.updatedAt)}
          </DetailField>
        </dl>
      </SectionCard>

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: organization.name })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          remove.mutate(target, {
            onSuccess: () => {
              setConfirmDelete(false)
              void navigate('/organizations')
            },
          })
        }}
      />
    </div>
  )
}

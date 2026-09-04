import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'

import { ImportPreviewPanel } from '../components/ImportPreviewPanel'
import { ImportRunPanel } from '../components/ImportRunPanel'
import {
  useCustomerImportConfiguration,
  useDeleteCustomerImportConfiguration,
} from '../hooks/useCustomerImportConfigurations'

/**
 * Fiche d'une configuration d'import.
 *
 * `mapping` et `validationRules` sont montrés **tels qu'ils sont stockés**, en
 * JSON formaté. Les rendre sous forme de tableau supposerait une structure que
 * le backend ne fixe pas, et masquerait tout ce qui n'y entre pas.
 *
 * Aucune trace d'exécution n'est affichée : il n'existe ni table `Import`, ni
 * route pour en produire une. Le §5 interdit d'inventer cet écran.
 *
 * L'essai, en revanche, y a sa place : éprouver la correspondance sur un
 * fichier ne crée rien et ne garde rien, mais c'est la seule façon de vérifier
 * qu'elle est juste avant de s'en servir.
 */
export function CustomerImportConfigurationDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const { data: configuration, isPending, error, refetch } = useCustomerImportConfiguration(id)
  const remove = useDeleteCustomerImportConfiguration()

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!configuration) return null

  const block = (value: Record<string, unknown> | null) =>
    value === null ? (
      <p className="text-sm text-muted-foreground">{t('integrations.imports.notConfigured')}</p>
    ) : (
      <pre className="max-h-80 overflow-auto rounded-md border bg-muted p-3 font-mono text-xs">
        {JSON.stringify(value, null, 2)}
      </pre>
    )

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={configuration.name}
        subtitle={`${configuration.sourceType} · ${configuration.fileFormat}`}
        editTo={`/integrations/imports/${id}/edit`}
        editPermission="customer_import_configurations.update"
        onDelete={() => setConfirmDelete(true)}
        deletePermission="customer_import_configurations.delete"
      />

      <SectionCard title={t('integrations.imports.sections.general')}>
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('integrations.fields.name')}>{configuration.name}</DetailField>
          <DetailField label={t('integrations.fields.isActive')}>
            <Badge variant={configuration.isActive ? 'outline' : 'secondary'}>
              {configuration.isActive ? t('common.active') : t('common.inactive')}
            </Badge>
          </DetailField>
          <DetailField label={t('integrations.fields.sourceType')}>
            <span className="font-mono text-sm">{configuration.sourceType}</span>
          </DetailField>
          <DetailField label={t('integrations.fields.fileFormat')}>
            <span className="font-mono text-sm">{configuration.fileFormat}</span>
          </DetailField>
        </dl>
      </SectionCard>

      <SectionCard
        title={t('integrations.fields.mapping')}
        description={t('integrations.imports.mappingHint')}
      >
        {block(configuration.mapping)}
      </SectionCard>

      <SectionCard
        title={t('integrations.fields.validationRules')}
        description={t('integrations.imports.validationRulesHint')}
      >
        {block(configuration.validationRules)}
      </SectionCard>

      <SectionCard
        title={t('integrations.imports.preview.title')}
        description={t('integrations.imports.preview.subtitle')}
      >
        <ImportPreviewPanel
          configurationId={id}
          hasMapping={configuration.mapping !== null}
        />
      </SectionCard>

      <SectionCard
        title={t('integrations.imports.run.title')}
        description={t('integrations.imports.run.subtitle')}
      >
        <ImportRunPanel
          configurationId={id}
          hasMapping={configuration.mapping !== null}
          isActive={configuration.isActive}
        />
      </SectionCard>

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: configuration.name })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          remove.mutate(id, {
            onSuccess: () => {
              setConfirmDelete(false)
              void navigate('/integrations/imports')
            },
          })
        }}
      />
    </div>
  )
}

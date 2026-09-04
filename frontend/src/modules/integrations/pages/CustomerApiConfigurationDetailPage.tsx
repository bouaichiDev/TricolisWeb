import { RefreshCw } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { ApiKeyCreatedDialog } from '../components/ApiKeyCreatedDialog'
import {
  useCustomerApiConfiguration,
  useDeleteCustomerApiConfiguration,
  useRotateCustomerApiKey,
} from '../hooks/useCustomerApiConfigurations'

/**
 * Fiche d'un accès API client.
 *
 * **La clé n'y figure pas**, ni en clair ni masquée : le serveur n'en garde
 * qu'un hash, et la ressource ne le renvoie pas. Ce qui se lit ici, ce sont les
 * restrictions et la dernière utilisation.
 *
 * `lastUsedAt` est posée par le serveur à chaque appel du client. Aucun
 * formulaire ne l'envoie : elle constate, elle ne se règle pas (§28).
 */
export function CustomerApiConfigurationDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()

  const [confirmDelete, setConfirmDelete] = useState(false)
  const [confirmRotate, setConfirmRotate] = useState(false)
  const [rotatedKey, setRotatedKey] = useState<string | null>(null)

  const { data: configuration, isPending, error, refetch } = useCustomerApiConfiguration(id)
  const remove = useDeleteCustomerApiConfiguration()
  const rotate = useRotateCustomerApiKey()

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!configuration) return null

  const allowedIps = configuration.allowedIps ?? []
  const permissions = configuration.permissions ?? []

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={configuration.name}
        editTo={`/integrations/api-access/${id}/edit`}
        editPermission="customer_api_configurations.update"
        onDelete={() => setConfirmDelete(true)}
        deletePermission="customer_api_configurations.delete"
        actions={
          <PermissionGuard permission="customer_api_configurations.rotate_key">
            <Button variant="outline" onClick={() => setConfirmRotate(true)}>
              <RefreshCw className="size-4" aria-hidden />
              {t('integrations.api.rotate')}
            </Button>
          </PermissionGuard>
        }
      />

      <SectionCard
        title={t('integrations.api.sections.general')}
        description={t('integrations.api.keyNotShown')}
      >
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('integrations.fields.name')}>{configuration.name}</DetailField>
          <DetailField label={t('integrations.fields.isActive')}>
            <Badge variant={configuration.isActive ? 'outline' : 'secondary'}>
              {configuration.isActive ? t('common.active') : t('common.inactive')}
            </Badge>
          </DetailField>
          <DetailField label={t('integrations.api.lastUsedAt')}>
            {configuration.lastUsedAt === null
              ? t('integrations.api.neverUsed')
              : formatDateTime(configuration.lastUsedAt)}
          </DetailField>
        </dl>
      </SectionCard>

      <SectionCard
        title={t('integrations.api.allowedIps')}
        description={
          allowedIps.length === 0
            ? t('integrations.api.allowedIpsEmpty')
            : t('integrations.api.allowedIpsHint')
        }
      >
        {allowedIps.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('integrations.api.anyIp')}</p>
        ) : (
          <ul className="flex flex-wrap gap-1.5">
            {allowedIps.map((entry) => (
              <li key={entry}>
                <Badge variant="outline" className="font-mono">
                  {entry}
                </Badge>
              </li>
            ))}
          </ul>
        )}
      </SectionCard>

      <SectionCard
        title={t('integrations.api.permissions')}
        description={t('integrations.api.permissionsHint')}
      >
        {permissions.length === 0 ? (
          <p className="text-sm text-muted-foreground">
            {t('integrations.api.permissionsEmpty')}
          </p>
        ) : (
          <ul className="flex flex-wrap gap-1.5">
            {permissions.map((code) => (
              <li key={code}>
                <Badge variant="secondary" className="font-mono">
                  {code}
                </Badge>
              </li>
            ))}
          </ul>
        )}
      </SectionCard>

      {/* La rotation invalide l'ancienne clé sur-le-champ : toute intégration
          qui l'emploie encore cessera de fonctionner. La confirmation le dit. */}
      <ConfirmDialog
        open={confirmRotate}
        onOpenChange={setConfirmRotate}
        title={t('integrations.api.rotate')}
        description={t('integrations.api.rotateConfirm')}
        confirmLabel={t('integrations.api.rotate')}
        isPending={rotate.isPending}
        onConfirm={() => {
          rotate.mutate(id, {
            onSuccess: (result) => {
              setConfirmRotate(false)
              setRotatedKey(result.apiKey)
            },
          })
        }}
      />

      <ApiKeyCreatedDialog
        apiKey={rotatedKey}
        configurationName={configuration.name}
        onClose={() => setRotatedKey(null)}
      />

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
              void navigate('/integrations/api-access')
            },
          })
        }}
      />
    </div>
  )
}

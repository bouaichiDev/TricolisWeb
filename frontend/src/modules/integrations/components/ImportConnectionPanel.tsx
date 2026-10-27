import { PlugZap, Play } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailField } from '@/shared/components/layout/DetailField'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import { useImportConnection, useRunImport, useTestImportConnection } from '../hooks/useImportConnection'
import type { ImportConnectionState } from '../types/importConnection'
import { FileConnectionForm } from './FileConnectionForm'
import { RestPullConnectionForm } from './RestPullConnectionForm'

/**
 * L'état d'exécution d'une configuration d'import : canal, fréquence, ce qui
 * manque — et jamais un faux « connexion OK » : seul le test, qui appelle
 * réellement la source sans rien importer, le dit.
 */
export function ImportConnectionPanel({ configurationId }: { configurationId: string }) {
  const { t } = useTranslation()
  const state = useImportConnection(configurationId)
  const test = useTestImportConnection(configurationId)
  const run = useRunImport(configurationId)

  if (state.isPending) return <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
  if (state.error) return <ErrorState error={state.error} onRetry={() => void state.refetch()} />

  const { connection, flags, mapping } = state.data
  const warnings = flagMessages(state.data, t)

  return (
    <div className="flex flex-col gap-5">
      <dl className="grid gap-x-8 sm:grid-cols-3">
        <DetailField label={t('integrations.imports.connection.channel')}>{t(`integrations.imports.connection.channels.${state.data.channel}`)}</DetailField>
        <DetailField label={t('integrations.imports.connection.frequency')}>{t(`integrations.imports.connection.frequencies.${state.data.frequency}`)}</DetailField>
        <DetailField label={t('integrations.imports.connection.orderSource')}><span className="font-mono text-sm">{state.data.orderSource}</span></DetailField>
      </dl>

      <ul aria-label={t('integrations.imports.connection.title')} className="flex flex-wrap gap-2">
        {state.data.ready && warnings.length === 0 ? <Badge variant="outline">{t('integrations.imports.connection.flags.ready')}</Badge> : null}
        {warnings.map((warning) => <li key={warning}><Badge variant="secondary">{warning}</Badge></li>)}
      </ul>
      {mapping.unknownTargets.length > 0 ? <p className="text-xs text-muted-foreground">{t('integrations.imports.connection.unknownTargets', { fields: mapping.unknownTargets.join(', ') })}</p> : null}

      {connection?.kind === 'file' ? (
        <div className="grid gap-2 rounded-md border bg-muted/30 p-3 text-sm">
          {connection.configured ? (
            <dl className="grid gap-x-8 sm:grid-cols-2">
              <DetailField label={t('integrations.imports.connection.file.host')}>{`${connection.driver}://${connection.host}${connection.port ? `:${connection.port}` : ''}`}</DetailField>
              <DetailField label={t('integrations.imports.connection.file.directory')}>{connection.directory || '/'}</DetailField>
              <DetailField label={t('integrations.imports.connection.file.pattern')}>{connection.filePattern}</DetailField>
              <DetailField label={t('integrations.imports.connection.file.afterImport')}>
                {t(`integrations.imports.connection.file.after.${connection.afterImport ?? 'keep'}`, { directory: connection.archiveDirectory ?? '' })}
              </DetailField>
            </dl>
          ) : (
            <p>{connection.problem ?? t('integrations.imports.connection.file.missing')}</p>
          )}
        </div>
      ) : null}

      {connection?.kind === 'file' ? (
        <PermissionGuard permission="customer_import_configurations.update">
          <FileConnectionForm configurationId={configurationId} connection={connection} />
        </PermissionGuard>
      ) : null}

      {connection?.kind === 'rest' ? (
        <PermissionGuard permission="customer_import_configurations.update">
          <RestPullConnectionForm configurationId={configurationId} connection={connection} />
        </PermissionGuard>
      ) : null}

      {connection !== null ? (
        <PermissionGuard permission="customer_import_configurations.update">
          <div className="flex flex-wrap items-center gap-3">
            <Button variant="outline" disabled={!flags.testable || test.isPending} onClick={() => test.mutate()}>
              <PlugZap className="size-4" aria-hidden /> {t('integrations.imports.connection.test')}
            </Button>
            <Button variant="outline" disabled={!state.data.ready || run.isPending} onClick={() => run.mutate()}>
              <Play className="size-4" aria-hidden /> {t('integrations.imports.connection.run')}
            </Button>
          </div>
          {test.data ? (
            <p role="status" className={test.data.ok ? 'text-sm text-success' : 'text-sm text-destructive'}>
              {t(test.data.ok ? 'integrations.imports.connection.testOk' : 'integrations.imports.connection.testFailed', { message: test.data.message })}
            </p>
          ) : null}
          {run.isSuccess ? <p role="status" className="text-sm">{t('integrations.imports.connection.runQueued')}</p> : null}
        </PermissionGuard>
      ) : null}
    </div>
  )
}

function flagMessages(state: ImportConnectionState, t: (key: string, options?: Record<string, string>) => string): string[] {
  const { flags, mapping } = state
  const messages: string[] = []

  if (flags.inactive) messages.push(t('integrations.imports.connection.flags.inactive'))
  if (flags.mappingMissing) messages.push(t('integrations.imports.connection.flags.mappingMissing'))
  if (flags.mappingIncomplete) messages.push(t('integrations.imports.connection.flags.mappingIncomplete', { fields: mapping.missingRequired.join(', ') }))
  if (mapping.problems.length > 0) messages.push(t('integrations.imports.connection.problems', { problems: mapping.problems.join(' ; ') }))
  if (flags.connectionMissing) messages.push(t('integrations.imports.connection.flags.connectionMissing'))
  if (flags.credentialMissing) messages.push(t('integrations.imports.connection.flags.credentialMissing'))
  if (state.connection !== null && !flags.testable) messages.push(t('integrations.imports.connection.flags.notTestable'))

  return messages
}

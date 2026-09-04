import { FileCheck2, RefreshCw } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { useExportJob, useRetryExportJob } from '../hooks/useExports'
import { isRetryable } from '../types/export'

/** Source du référentiel pour `export_jobs.status`, semée en Phase 6. */
const EXPORT_JOB_SOURCE = 'export_job'

/**
 * Fiche d'un envoi.
 *
 * **Ni modification, ni suppression** : la route n'expose que `index`, `store`,
 * `show` et `retry`. Ce qui est écrit ici a eu lieu, et une correction
 * s'enregistre comme un envoi de plus.
 *
 * **Aucun bouton de téléchargement.** `hasFile` dit qu'un fichier a été produit,
 * mais il n'existe ni route `download`, ni permission `export_jobs.download`.
 * Construire une URL depuis `storagePath` serait la seule autre voie : le §58
 * l'interdit, et le serveur ne renvoie de toute façon pas ce chemin — à juste
 * titre. L'écran dit donc ce qu'il sait, sans proposer une action qui
 * échouerait.
 *
 * `storagePath` n'est affiché nulle part : c'est un chemin interne (§55).
 */
export function ExportJobDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams<{ id: string }>()

  const { data: job, isPending, error, refetch } = useExportJob(id)
  const retry = useRetryExportJob()

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!job) return null

  const dash = <span className="text-muted-foreground">—</span>
  const retryable = isRetryable(job)

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={job.fileName ?? t('exports.jobs.untitled')}
        description={formatDateTime(job.generatedAt)}
        actions={
          retryable ? (
            <PermissionGuard permission="export_jobs.retry">
              <Button
                variant="outline"
                disabled={retry.isPending}
                onClick={() => retry.mutate(id)}
              >
                <RefreshCw className="size-4" aria-hidden />
                {t('exports.jobs.retry')}
              </Button>
            </PermissionGuard>
          ) : null
        }
      />

      <SectionCard
        title={t('exports.jobs.sections.delivery')}
        description={retryable ? t('exports.jobs.pendingHint') : t('exports.jobs.sentHint')}
      >
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('exports.jobs.fields.status')}>
            <StatusBadge status={job.status} source={EXPORT_JOB_SOURCE} />
          </DetailField>
          <DetailField label={t('exports.jobs.fields.attemptCount')}>
            {String(job.attemptCount)}
          </DetailField>
          <DetailField label={t('exports.jobs.fields.generatedAt')}>
            {job.generatedAt === null ? dash : formatDateTime(job.generatedAt)}
          </DetailField>
          <DetailField label={t('exports.jobs.fields.sentAt')}>
            {job.sentAt === null ? t('exports.jobs.notSent') : formatDateTime(job.sentAt)}
          </DetailField>
          <DetailField label={t('exports.jobs.fields.fileName')}>
            {job.fileName ?? dash}
          </DetailField>
          <DetailField label={t('exports.jobs.fields.file')}>
            {job.hasFile ? (
              <span className="flex items-center gap-1.5 text-sm">
                <FileCheck2 className="size-4 text-success" aria-hidden />
                {t('exports.jobs.fileGenerated')}
              </span>
            ) : (
              t('exports.jobs.noFile')
            )}
          </DetailField>
        </dl>

        {job.errorMessage === null ? null : (
          <div className="mt-4 rounded-md border border-destructive/30 bg-destructive/10 p-3">
            <p className="text-xs font-medium text-destructive">
              {t('exports.jobs.fields.errorMessage')}
            </p>
            <p className="mt-1 text-sm">{job.errorMessage}</p>
          </div>
        )}
      </SectionCard>

      <SectionCard
        title={t('exports.jobs.sections.target')}
        description={t('exports.jobs.targetHint')}
      >
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('exports.jobs.fields.configuration')}>
            {job.configuration === undefined ? (
              job.configurationId
            ) : (
              <Link
                to={`/integrations/exports?configurationId=${job.configurationId}`}
                className="hover:underline"
              >
                {job.configuration.name}
              </Link>
            )}
          </DetailField>
          <DetailField label={t('exports.jobs.fields.channel')}>
            {job.configuration === undefined ? (
              dash
            ) : (
              <span className="flex gap-1">
                <Badge variant="outline">
                  {t(`exports.transports.${job.configuration.transport}`, {
                    defaultValue: job.configuration.transport,
                  })}
                </Badge>
                <Badge variant="outline">{job.configuration.format.toUpperCase()}</Badge>
              </span>
            )}
          </DetailField>
          <DetailField label={t('exports.jobs.fields.entityType')}>
            {job.entityType === null ? (
              dash
            ) : (
              t(`entities.${job.entityType}`, { defaultValue: job.entityType })
            )}
          </DetailField>
          <DetailField label={t('exports.jobs.fields.entity')}>
            {job.entityId === null ? (
              dash
            ) : job.entityType === 'invoice' ? (
              <Link to={`/billing/invoices/${job.entityId}`} className="hover:underline">
                {t('exports.jobs.openInvoice')}
              </Link>
            ) : (
              <span className="font-mono text-xs">{job.entityId}</span>
            )}
          </DetailField>
        </dl>
      </SectionCard>
    </div>
  )
}

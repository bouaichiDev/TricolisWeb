import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import { useImportRun, useImportRuns } from '../hooks/useImportRuns'
import type { ImportRun, ImportRunStatus } from '../types/importRun'

const TONE: Record<ImportRunStatus, 'outline' | 'secondary' | 'destructive'> = {
  completed: 'outline',
  completed_with_errors: 'secondary',
  rejected: 'destructive',
  failed: 'destructive',
}

const dateTime = new Intl.DateTimeFormat('fr-CH', { dateStyle: 'short', timeStyle: 'short' })

/**
 * L'historique d'une configuration d'import : chaque dépôt ou relève, son
 * bilan, et — en dépliant une ligne — les commandes refusées et pourquoi.
 */
export function ImportRunsPanel({ configurationId }: { configurationId: string }) {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const [openId, setOpenId] = useState<string | null>(null)
  const runs = useImportRuns(configurationId, page)

  if (runs.isPending) return <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
  if (runs.error) return <ErrorState error={runs.error} onRetry={() => void runs.refetch()} />
  if (runs.data.data.length === 0) return <p className="text-sm text-muted-foreground">{t('integrations.imports.runs.empty')}</p>

  const { meta } = runs.data

  return (
    <div className="grid gap-3">
      <ul className="grid gap-2" aria-label={t('integrations.imports.runs.title')}>
        {runs.data.data.map((run) => (
          <li key={run.id} className="rounded-md border p-3 text-sm">
            <button type="button" className="flex w-full flex-wrap items-center gap-x-4 gap-y-1 text-left" aria-expanded={openId === run.id} onClick={() => setOpenId(openId === run.id ? null : run.id)}>
              <span className="tabular-nums">{dateTime.format(new Date(run.finishedAt))}</span>
              <Badge variant={TONE[run.status]}>{t(`integrations.imports.runs.status.${run.status}`)}</Badge>
              <span className="text-muted-foreground">{t(`integrations.imports.runs.channel.${run.channel}`)}</span>
              {run.fileName ? <span className="font-mono text-xs">{run.fileName}</span> : null}
              <span className="ml-auto">{summary(run, t)}</span>
            </button>
            {openId === run.id ? <ImportRunDetail configurationId={configurationId} run={run} /> : null}
          </li>
        ))}
      </ul>
      {meta.lastPage > 1 ? (
        <div className="flex items-center justify-end gap-2">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>{t('common.previous')}</Button>
          <span className="text-xs text-muted-foreground">{page} / {meta.lastPage}</span>
          <Button variant="outline" size="sm" disabled={page >= meta.lastPage} onClick={() => setPage(page + 1)}>{t('common.next')}</Button>
        </div>
      ) : null}
    </div>
  )
}

function ImportRunDetail({ configurationId, run }: { configurationId: string; run: ImportRun }) {
  const { t } = useTranslation()
  const detail = useImportRun(configurationId, run.id)

  return (
    <div className="mt-3 grid gap-2 border-t pt-3">
      {run.errorMessage ? <p className="text-destructive">{run.errorMessage}</p> : null}
      {run.triggeredBy ? <p className="text-xs text-muted-foreground">{t('integrations.imports.runs.by', { name: run.triggeredBy.name })}</p> : null}
      {detail.isPending ? <p className="text-xs text-muted-foreground">{t('common.loading')}</p> : null}
      {detail.data?.errors?.length ? (
        <ul className="grid gap-1">
          {detail.data.errors.map((error) => (
            <li key={`${error.index}-${error.reference ?? ''}`}>
              <span className="font-medium">{t('integrations.imports.runs.order', { index: error.index + 1, reference: error.reference ?? '—' })}</span>{' '}
              <span className="text-muted-foreground">{Object.values(error.errors).flat().join(' ')}</span>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  )
}

function summary(run: ImportRun, t: (key: string, options?: Record<string, number>) => string): string {
  if (run.status === 'failed') return t('integrations.imports.runs.failedSummary')

  return t('integrations.imports.runs.summary', { created: run.counts.created, updated: run.counts.updated, skipped: run.counts.skipped, refused: run.counts.invalid + run.counts.failed })
}

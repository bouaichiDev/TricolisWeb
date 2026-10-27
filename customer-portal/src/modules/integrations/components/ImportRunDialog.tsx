import { errorMessage } from '@/shared/api/errors'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'
import { formatDateTime } from '@/shared/utils/format'

import { useImportRun } from '../hooks/useImportRuns'
import { importRunChannelLabels, importRunStatusLabels, runSummary } from '../importRunTypes'

/** La fiche d'un import : bilan, auteur, et chaque commande refusée avec ses messages. */
export function ImportRunDialog({ runId, onClose }: { runId: string | null; onClose: () => void }) {
  const run = useImportRun(runId)

  return (
    <Dialog open={runId !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Détail de l’import</DialogTitle>
          <DialogDescription>
            {run.data ? `${run.data.configuration?.name ?? ''} — ${formatDateTime(run.data.finishedAt)}` : 'Chargement…'}
          </DialogDescription>
        </DialogHeader>
        {run.isPending ? (
          <InlineLoader />
        ) : run.isError ? (
          <ErrorState message={errorMessage(run.error)} onRetry={() => void run.refetch()} />
        ) : (
          <div className="grid gap-3 text-sm">
            <p>
              <span className="font-medium">{importRunStatusLabels[run.data.status]}</span> · {importRunChannelLabels[run.data.channel]}
              {run.data.fileName ? ` · ${run.data.fileName}` : ''}
            </p>
            <p>{runSummary(run.data)}</p>
            {run.data.triggeredBy ? <p className="text-muted-foreground">Déposé par {run.data.triggeredBy.name}</p> : null}
            {run.data.errorMessage ? <p className="text-destructive">{run.data.errorMessage}</p> : null}
            {run.data.errors && run.data.errors.length > 0 ? (
              <ul className="grid gap-2" aria-label="Commandes refusées">
                {run.data.errors.map((error) => (
                  <li key={`${error.index}-${error.reference ?? ''}`} className="rounded-md border p-2">
                    <p className="font-medium">Commande n° {error.index + 1}{error.reference ? ` — ${error.reference}` : ''}</p>
                    <p className="text-muted-foreground">{Object.values(error.errors).flat().join(' ') || 'Refusée.'}</p>
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}

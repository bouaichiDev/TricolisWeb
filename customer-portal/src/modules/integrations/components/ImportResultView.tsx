import { Link } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import { InlineLoader } from '@/shared/components/feedback/States'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'

import { useImportResult } from '../hooks/useIntegrations'
import type { ImportResult } from '../types'
import { flattenErrors, outcomeLabels } from '../utils/labels'

/**
 * Le résumé final (étape 7) : commandes créées, ignorées, refusées — et,
 * pour chaque refus, pourquoi. Un import parti en file est relu jusqu'à ce
 * qu'il soit terminé.
 */
export function ImportResultView({ result }: { result: ImportResult }) {
  const queued = result.status === 'queued'
  const polled = useImportResult(queued ? (result.token ?? null) : null)
  const current = queued ? polled.data : result

  if (current === undefined || current.status === 'queued' || current.status === 'running') {
    return <InlineLoader label="Import en cours de traitement…" />
  }

  if (current.status === 'failed') {
    return (
      <Alert variant="destructive">
        <AlertDescription>{current.message ?? 'L’import n’a pas pu aboutir.'}</AlertDescription>
      </Alert>
    )
  }

  const created = (current.outcomes ?? []).filter((outcome) => outcome.status === 'created' && outcome.orderId)

  return (
    <div className="grid gap-4 text-sm">
      <section aria-label="Résultat" className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {[
          ['Créées', current.created ?? 0],
          ['Mises à jour', current.updated ?? 0],
          ['Déjà importées', current.skipped ?? 0],
          ['Refusées', (current.invalid ?? 0) + (current.failed ?? 0)],
        ].map(([label, value]) => (
          <div key={label} className="rounded-md border p-3">
            <p className="text-muted-foreground">{label}</p>
            <p className="text-lg font-semibold tabular-nums">{value}</p>
          </div>
        ))}
      </section>

      {created.length > 0 && (
        <ul aria-label="Commandes créées" className="flex flex-wrap gap-2">
          {created.map((outcome) => (
            <li key={outcome.orderId}>
              <Link to={paths.order(outcome.orderId ?? '')} className="text-primary hover:underline">
                {outcome.orderNumber ?? outcome.reference}
              </Link>
            </li>
          ))}
        </ul>
      )}

      {(current.errors ?? []).length > 0 && (
        <section aria-label="Erreurs" className="grid gap-2">
          <h3 className="font-medium">À corriger dans votre fichier</h3>
          {(current.errors ?? []).map((outcome) => (
            <div key={outcome.index} className="rounded-md border border-red-200 p-2">
              <p className="font-medium">{outcome.reference ?? `Commande ${outcome.index + 1}`} — {outcomeLabels[outcome.status]}</p>
              {flattenErrors(outcome.errors).map((message) => <p key={message} className="text-xs text-red-700">{message}</p>)}
            </div>
          ))}
        </section>
      )}
    </div>
  )
}

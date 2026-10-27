import { Badge } from '@/shared/components/ui/badge'

import type { ImportPreview, PreviewOrder } from '../types'
import { flattenErrors } from '../utils/labels'

const SAMPLE_COLUMNS = 6

const statusBadge: Record<PreviewOrder['status'], { label: string; className: string }> = {
  valid: { label: 'Valide', className: 'border-emerald-300 text-emerald-700' },
  invalid: { label: 'À corriger', className: 'border-red-300 text-red-700' },
  duplicate: { label: 'Déjà importée', className: 'border-amber-300 text-amber-700' },
}

const duplicateHint = { skip: 'sera ignorée', update: 'sera mise à jour si elle est encore modifiable', reject: 'sera refusée' }

/**
 * Ce que l'import ferait (étapes 3 à 5) : colonnes lues, correspondance
 * appliquée, verdict par commande. Rien n'est encore créé.
 */
export function ImportPreviewView({ preview }: { preview: ImportPreview }) {
  const columns = preview.headers.slice(0, SAMPLE_COLUMNS)
  const { summary } = preview

  return (
    <div className="grid gap-5 text-sm">
      <section aria-label="Résumé" className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {[
          ['Lignes lues', preview.rowCount],
          ['Commandes', summary.orderCount],
          ['Valides', summary.valid],
          ['À corriger', summary.invalid],
        ].map(([label, value]) => (
          <div key={label} className="rounded-md border p-3">
            <p className="text-muted-foreground">{label}</p>
            <p className="text-lg font-semibold tabular-nums">{value}</p>
          </div>
        ))}
      </section>
      {summary.duplicates > 0 && <p className="text-amber-700">{summary.duplicates} commande(s) déjà importée(s).</p>}

      <section aria-label="Aperçu du fichier" className="grid gap-2">
        <h3 className="font-medium">Colonnes lues ({preview.headers.length})</h3>
        <div className="overflow-x-auto rounded-md border">
          <table className="w-full text-left text-xs">
            <thead className="bg-muted/50">
              <tr>{columns.map((column) => <th key={column} className="px-2 py-1 font-medium">{column}</th>)}</tr>
            </thead>
            <tbody>
              {preview.sampleRows.map((row, index) => (
                <tr key={index} className="border-t">
                  {columns.map((column) => <td key={column} className="px-2 py-1">{String(row[column] ?? '')}</td>)}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section aria-label="Correspondance" className="grid gap-2">
        <h3 className="font-medium">Correspondance</h3>
        <ul className="grid gap-1 sm:grid-cols-2">
          {preview.mapping.map((pair) => (
            <li key={pair.target} className="flex justify-between gap-2 rounded border px-2 py-1 text-xs">
              <span className="text-muted-foreground">{pair.source ?? `valeur fixe « ${String(pair.constant)} »`}</span>
              <span className="font-mono">{pair.target}</span>
            </li>
          ))}
        </ul>
      </section>

      <section aria-label="Commandes" className="grid gap-2">
        <h3 className="font-medium">Commandes</h3>
        <ul className="grid gap-2">
          {summary.orders.map((order) => (
            <li key={order.index} className="rounded-md border p-2">
              <div className="flex items-center justify-between gap-2">
                <span className="font-medium">{order.reference ?? `Commande ${order.index + 1}`}</span>
                <Badge variant="outline" className={statusBadge[order.status].className}>{statusBadge[order.status].label}</Badge>
              </div>
              <p className="text-xs text-muted-foreground">{order.lineCount} ligne(s), {order.serviceCount} prestation(s)</p>
              {order.duplicateAction && <p className="text-xs text-amber-700">Déjà importée : {duplicateHint[order.duplicateAction]}.</p>}
              {flattenErrors(order.errors).map((message) => <p key={message} className="text-xs text-red-700">{message}</p>)}
            </li>
          ))}
        </ul>
      </section>
    </div>
  )
}

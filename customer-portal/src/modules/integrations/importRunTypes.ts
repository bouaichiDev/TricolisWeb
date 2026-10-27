/** Une exécution d'import du client (historique, décision Phase 6 levée le 2026-10-10). */
export type ImportRunStatus = 'completed' | 'completed_with_errors' | 'rejected' | 'failed'

export interface ImportRunError {
  index: number
  reference: string | null
  status: string
  errors: Record<string, string[]>
}

export interface ImportRun {
  id: string
  configuration?: { id: string; name: string }
  channel: 'internal' | 'portal' | 'pull'
  source: string
  status: ImportRunStatus
  fileName: string | null
  counts: { rows: number; orders: number; created: number; updated: number; skipped: number; invalid: number; failed: number }
  /** Rendu par la fiche seulement. */
  errors?: ImportRunError[]
  errorMessage: string | null
  /** Seulement pour un dépôt fait dans le portail : le personnel du transporteur n'est pas nommé. */
  triggeredBy: { id: string; name: string } | null
  startedAt: string | null
  finishedAt: string
}

export interface ImportRunFilters {
  status?: ImportRunStatus
  page?: number
  perPage?: number
}

export const importRunStatusLabels: Record<ImportRunStatus, string> = {
  completed: 'Terminé',
  completed_with_errors: 'Terminé avec erreurs',
  rejected: 'Fichier refusé',
  failed: 'Échec',
}

export const importRunChannelLabels: Record<ImportRun['channel'], string> = {
  portal: 'Dépôt dans le portail',
  pull: 'Relève automatique',
  internal: 'Par votre transporteur',
}

/** Le bilan d'une exécution en une ligne. */
export function runSummary(run: ImportRun): string {
  if (run.status === 'failed') return 'Rien importé'

  const refused = run.counts.invalid + run.counts.failed

  return `${run.counts.created} créée(s), ${run.counts.skipped} déjà importée(s), ${refused} refusée(s)`
}

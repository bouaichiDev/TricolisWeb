/** Une exécution d'import, telle que l'historique la rend (décision Phase 6 levée le 2026-10-10). */
export type ImportRunStatus = 'completed' | 'completed_with_errors' | 'rejected' | 'failed'

export interface ImportRunCounts {
  rows: number
  orders: number
  created: number
  updated: number
  skipped: number
  invalid: number
  failed: number
}

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
  counts: ImportRunCounts
  /** Rendu par la fiche seulement. */
  errors?: ImportRunError[]
  errorMessage: string | null
  triggeredBy: { id: string; name: string } | null
  startedAt: string | null
  finishedAt: string
}

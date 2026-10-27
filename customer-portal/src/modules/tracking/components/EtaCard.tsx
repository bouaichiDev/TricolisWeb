import { Clock } from 'lucide-react'

import { formatDateTime } from '@/shared/utils/format'

import type { Eta } from '../types'

/**
 * L'heure d'arrivée estimée, telle que le backend la donne — ou son absence,
 * dite en clair. Aucune estimation n'est calculée ici (§18).
 */
export function EtaCard({ eta }: { eta: Eta | null }) {
  return (
    <div className="flex items-start gap-3 rounded-lg border p-3">
      <Clock className="text-muted-foreground mt-0.5 size-5 shrink-0" aria-hidden />
      <div>
        <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Arrivée estimée</p>
        {eta?.available && eta.estimatedAt ? (
          <p className="text-lg font-semibold" data-testid="eta-value">
            {formatDateTime(eta.estimatedAt)}
          </p>
        ) : (
          <p className="text-muted-foreground text-sm">ETA indisponible</p>
        )}
      </div>
    </div>
  )
}

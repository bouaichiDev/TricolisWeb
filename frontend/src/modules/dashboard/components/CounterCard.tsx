import { Link } from 'react-router-dom'

import type { Counter } from '../hooks/useCounters'
import { Skeleton } from '@/shared/components/ui/skeleton'

/**
 * Carte de compteur.
 *
 * Un total absent affiche un tiret, jamais un zéro : « aucun client » et « le
 * chiffre n'a pas pu être lu » ne sont pas la même information.
 */
export function CounterCard({ counter, label }: { counter: Counter; label: string }) {
  return (
    <Link
      to={counter.to}
      className="flex flex-col gap-2 rounded-lg border bg-card p-5 transition-colors hover:border-primary/40 hover:bg-accent/40"
    >
      <span className="text-sm text-muted-foreground">{label}</span>
      {counter.isPending ? (
        <Skeleton className="h-8 w-16" />
      ) : (
        <span className="text-3xl font-semibold tabular-nums">{counter.total ?? '—'}</span>
      )}
    </Link>
  )
}

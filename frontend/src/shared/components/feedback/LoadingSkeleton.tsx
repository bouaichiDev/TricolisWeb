import { Skeleton } from '@/shared/components/ui/skeleton'

/**
 * Squelette de fiche.
 *
 * Le §32 ecarte les indicateurs plein ecran pour chaque petite requete : un
 * squelette garde la mise en page stable, donc l'oeil ne saute pas quand les
 * donnees arrivent.
 */
export function ListSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="flex flex-col gap-3">
      {Array.from({ length: rows }, (_, index) => (
        <Skeleton key={index} className="h-9 w-full" />
      ))}
    </div>
  )
}

export function DetailSkeleton({ rows = 6 }: { rows?: number }) {
  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-4 w-40" />
      </div>
      <div className="rounded-lg border bg-card p-6">
        <div className="grid gap-5 sm:grid-cols-2">
          {Array.from({ length: rows }, (_, index) => (
            <div key={index} className="flex flex-col gap-1.5">
              <Skeleton className="h-3 w-24" />
              <Skeleton className="h-4 w-40" />
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

import { Skeleton } from '@/shared/components/ui/skeleton'

/**
 * Ce qu'on montre pendant que le tableau de bord se compose.
 *
 * Huit tuiles neutres, sans chiffre ni titre. La page ne peut pas deviner ce
 * qu'elle affichera — cela dépend des rôles de qui regarde — et un squelette
 * qui mimerait une disposition précise en promettrait une qui n'arrivera
 * peut-être pas.
 *
 * Surtout, il remplace ce qui se faisait avant : afficher les quatre anciennes
 * cartes le temps de la requête. Elles montraient à chacun un tableau de bord
 * qui n'était pas le sien, puis disparaissaient — ce qui ressemble à une panne
 * bien plus qu'à un chargement.
 */
export function DashboardSkeleton() {
  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-6 lg:grid-cols-12">
      {Array.from({ length: 8 }, (_, index) => (
        <div key={index} className="md:col-span-3">
          <Skeleton className="h-28 w-full rounded-lg" />
        </div>
      ))}
    </div>
  )
}

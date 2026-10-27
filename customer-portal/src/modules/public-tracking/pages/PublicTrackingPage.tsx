import { SearchX } from 'lucide-react'
import { useParams } from 'react-router-dom'

import { ApiError, errorMessage } from '@/shared/api/errors'
import { EmptyState, ErrorState, InlineLoader } from '@/shared/components/feedback/States'

import { PublicTrackingBrand } from '../components/PublicTrackingBrand'
import { PublicTrackingContent } from '../components/PublicTrackingContent'
import { usePublicTracking } from '../hooks/usePublicTracking'

/**
 * Suivi public d'une commande, ouvert depuis un SMS ou un courriel (§29).
 *
 * Pas de barre latérale ni de session : une colonne étroite, lisible sur
 * téléphone. Lien invalide, expiré ou révoqué : un seul message, le même
 * que l'API — rien qui distingue un lien révoqué d'un lien inventé.
 *
 * `no-referrer` et `noindex` : l'URL porte le secret du lien.
 */
export function PublicTrackingPage() {
  const { token = '' } = useParams()
  const tracking = usePublicTracking(token)
  const unavailable = tracking.error instanceof ApiError && tracking.error.status === 404

  return (
    <div className="bg-muted/40 min-h-screen">
      <title>Suivi de livraison</title>
      <meta name="referrer" content="no-referrer" />
      <meta name="robots" content="noindex, nofollow" />

      <main className="mx-auto flex w-full max-w-2xl flex-col gap-6 px-4 py-6 sm:py-10">
        <PublicTrackingBrand token={token} organization={tracking.data?.organization ?? null} />

        {tracking.isPending ? (
          <InlineLoader label="Chargement du suivi…" />
        ) : unavailable ? (
          <EmptyState title="Tracking indisponible" icon={SearchX}>
            Ce lien de suivi n’est pas valable ou n’est plus actif. Contactez l’expéditeur de votre commande.
          </EmptyState>
        ) : tracking.isError ? (
          <ErrorState title="Suivi momentanément indisponible" message={errorMessage(tracking.error)} onRetry={() => void tracking.refetch()} />
        ) : (
          <PublicTrackingContent tracking={tracking.data} />
        )}
      </main>
    </div>
  )
}

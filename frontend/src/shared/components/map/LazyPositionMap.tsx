import { lazy, Suspense } from 'react'
import { useTranslation } from 'react-i18next'

import type { MapPoint } from './PositionMap'

/**
 * La carte, chargée seulement quand elle s'affiche.
 *
 * Leaflet et son CSS pèsent environ 260 ko — un quart du paquet — pour un
 * composant qu'on ne voit que sur les commandes en cours de livraison. Le
 * charger avec le reste ferait payer ce poids à chaque écran de
 * l'application, y compris à ceux qui ne montrent jamais de carte.
 */
const PositionMap = lazy(() =>
  import('./PositionMap').then((module) => ({ default: module.PositionMap })),
)

export function LazyPositionMap({ points, className }: { points: MapPoint[]; className?: string }) {
  const { t } = useTranslation()

  return (
    <Suspense
      fallback={
        // Meme hauteur que la carte : sans cela, le contenu dessous sauterait
        // au moment ou elle arrive.
        <div className="flex h-64 w-full items-center justify-center rounded-lg border text-sm text-muted-foreground">
          {t('common.loading')}
        </div>
      }
    >
      <PositionMap points={points} className={className} />
    </Suspense>
  )
}

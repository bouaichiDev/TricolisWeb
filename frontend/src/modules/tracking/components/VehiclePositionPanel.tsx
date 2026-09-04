import { MapPin, Navigation } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { LazyPositionMap } from '@/shared/components/map/LazyPositionMap'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { useOrderPositions } from '../hooks/useTracking'

interface VehiclePositionPanelProps {
  orderId: string
  /** Vrai quand une étape du parcours est suivie en direct. */
  enabled: boolean
}

/**
 * Position du véhicule, réinterrogée toutes les trente secondes.
 *
 * Le jeton du fournisseur **ne passe pas par ici** : le navigateur interroge
 * Tricolis, qui interroge la télématique. Appeler Flespi depuis le navigateur
 * exposerait un jeton donnant accès à l'historique de tous les véhicules.
 *
 * La carte trace le chemin parcouru et marque la dernière position. Les
 * coordonnées restent affichées dessous : elles se copient, se dictent au
 * téléphone, et survivent à un fond de plan qui ne charge pas.
 */
export function VehiclePositionPanel({ orderId, enabled }: VehiclePositionPanelProps) {
  const { t } = useTranslation()
  const positions = useOrderPositions(orderId, enabled)

  if (!enabled) return null

  const data = positions.data
  const points = data?.points ?? []
  const last = points.at(-1)

  return (
    <section className="flex flex-col gap-2 rounded-lg border bg-card p-3">
      <p className="flex items-center gap-2 text-sm font-medium">
        <Navigation className="size-4 text-muted-foreground" aria-hidden />
        {t('tracking.vehiclePosition')}
      </p>

      {positions.isPending ? (
        <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
      ) : data?.reason === 'not_configured' ? (
        <Alert>
          <AlertDescription>{t('tracking.positionNotConfigured')}</AlertDescription>
        </Alert>
      ) : data?.reason === 'no_reference' ? (
        <Alert>
          <AlertDescription>{t('tracking.positionNoReference')}</AlertDescription>
        </Alert>
      ) : last === undefined ? (
        <p className="text-sm text-muted-foreground">{t('tracking.positionNone')}</p>
      ) : (
        <>
          <LazyPositionMap
            points={points.map((point) => ({
              latitude: point.latitude,
              longitude: point.longitude,
              label:
                point.occurredAt === null ? undefined : formatDateTime(point.occurredAt),
            }))}
          />

          <p className="font-mono text-sm">
            {last.latitude}, {last.longitude}
          </p>
          <p className="text-xs text-muted-foreground">
            {last.occurredAt === null
              ? t('tracking.positionUndated')
              : t('tracking.positionAt', { at: formatDateTime(last.occurredAt) })}
            {' · '}
            {t('tracking.positionCount', { count: points.length })}
          </p>

          <Button variant="outline" size="sm" className="w-fit" asChild>
            {/* La carte integree suffit a situer ; l'ouvrir ailleurs donne
                l'itineraire, la vue satellite et le partage. */}
            <a
              href={`https://www.openstreetmap.org/?mlat=${last.latitude}&mlon=${last.longitude}#map=15/${last.latitude}/${last.longitude}`}
              target="_blank"
              rel="noreferrer noopener"
            >
              <MapPin className="size-4" aria-hidden />
              {t('tracking.openMap')}
            </a>
          </Button>
        </>
      )}
    </section>
  )
}

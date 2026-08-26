import { Check, Package, Users } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import type { Tour } from '@/modules/tours/types/tour'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { formatDate } from '@/shared/utils/format'

interface TourDraftPanelProps {
  tour: Tour
  selected: boolean
  onSelect: () => void
}

/**
 * Une tournée en préparation, choisie pour recevoir.
 *
 * Les totaux affichés sont ceux que le serveur a recalculés : les recompter
 * ici donnerait un chiffre qui diffère de celui de la fiche.
 */
export function TourDraftPanel({ tour, selected, onSelect }: TourDraftPanelProps) {
  const { t } = useTranslation()

  return (
    <li>
      <div
        className={`flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 ${
          selected ? 'border-primary bg-muted' : ''
        }`}
      >
        <button type="button" onClick={onSelect} className="min-w-0 flex-1 text-left">
          <span className="flex items-center gap-2 font-medium">
            {selected ? <Check className="size-4 text-primary" aria-hidden /> : null}
            {tour.tourNumber}
          </span>
          <span className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
            {tour.tourDate === null ? null : <span>{formatDate(tour.tourDate)}</span>}
            <span className="flex items-center gap-1">
              <Package className="size-3" aria-hidden />
              {tour.totalPackages}
            </span>
            <span className="flex items-center gap-1">
              <Users className="size-3" aria-hidden />
              {tour.totalCustomers}
            </span>
            {tour.stopCount === undefined ? null : (
              <span>{t('planning.stopCount', { count: tour.stopCount })}</span>
            )}
          </span>
        </button>

        <span className="flex items-center gap-2">
          <StatusBadge status={tour.status} source="tour" />
          <Link to={`/tours/${tour.id}`} className="text-sm text-primary hover:underline">
            {t('common.open')}
          </Link>
        </span>
      </div>
    </li>
  )
}

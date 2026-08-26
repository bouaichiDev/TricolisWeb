import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import type { Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { formatDate } from '@/shared/utils/format'

import type { Tour } from '../types/tour'

/**
 * Les colonnes du tableau des tournées.
 *
 * Séparées de l'écran parce qu'elles sont sa moitié la plus verbeuse et la
 * moins mouvante : ce qui bouge, c'est la façon de lire la liste, pas ce qu'une
 * ligne montre.
 */
export function useTourColumns(): Column<Tour>[] {
  const { t } = useTranslation()

  return [
    {
      key: 'tourNumber',
      header: t('tours.fields.tourNumber'),
      cell: (row) => (
        <Link to={`/tours/${row.id}`} className="font-medium text-primary hover:underline">
          {row.tourNumber}
        </Link>
      ),
    },
    {
      key: 'tourDate',
      header: t('tours.fields.tourDate'),
      cell: (row) => (row.tourDate === null ? '—' : formatDate(row.tourDate)),
    },
    {
      key: 'stops',
      header: t('tours.fields.stops'),
      hideOnMobile: true,
      cell: (row) => row.stopCount ?? '—',
    },
    {
      key: 'load',
      header: t('tours.fields.load'),
      hideOnMobile: true,
      cell: (row) =>
        t('tours.loadSummary', {
          packages: row.totalPackages,
          customers: row.totalCustomers,
        }),
    },
    {
      key: 'status',
      header: t('tours.fields.status'),
      cell: (row) => <StatusBadge status={row.status} source="tour" />,
    },
  ]
}

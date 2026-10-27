import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'

import { errorMessage } from '@/shared/api/errors'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { StatusFilter } from '@/shared/components/data/StatusFilter'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { formatDate, formatDateTime } from '@/shared/utils/format'

import { toursApi } from '../api/toursApi'
import type { Tour } from '../types'

const STATUSES = [
  { value: 'planned', label: 'Planifiée' },
  { value: 'confirmed', label: 'Confirmée' },
  { value: 'in_progress', label: 'En cours' },
  { value: 'completed', label: 'Terminée' },
  { value: 'cancelled', label: 'Annulée' },
]

const columns: Column<Tour>[] = [
  {
    key: 'number',
    header: 'Tournée',
    cell: (tour) => (
      <Link to={`/tours/${tour.id}`} className="text-primary font-medium hover:underline">
        {tour.tourNumber}
      </Link>
    ),
  },
  { key: 'date', header: 'Date', cell: (tour) => formatDate(tour.tourDate) },
  { key: 'start', header: 'Départ prévu', hideOnMobile: true, cell: (tour) => formatDateTime(tour.plannedStartAt) },
  { key: 'driver', header: 'Chauffeur', hideOnMobile: true, cell: (tour) => tour.driver?.name ?? '—' },
  { key: 'vehicle', header: 'Véhicule', hideOnMobile: true, cell: (tour) => tour.vehicle?.code ?? '—' },
  { key: 'status', header: 'Statut', cell: (tour) => <StatusBadge code={tour.status} label={tour.statusLabel} /> },
]

/** Les tournées que le transporteur a attribuées au fournisseur. */
export function ToursPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState<number | undefined>(undefined)
  const filters = { search, status, page, perPage }
  const tours = useQuery({ queryKey: ['tours', filters], queryFn: () => toursApi.list(filters), placeholderData: keepPreviousData })

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Tournées" description="Les tournées que votre transporteur vous a confiées." />
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <SearchInput value={search} onChange={(value) => { setSearch(value); setPage(1) }} label="Rechercher une tournée" placeholder="Numéro de tournée" />
        <StatusFilter value={status} onChange={(value) => { setStatus(value); setPage(1) }} options={STATUSES} />
      </div>
      <DataTable
        columns={columns}
        rows={tours.data?.data ?? []}
        rowKey={(tour) => tour.id}
        meta={tours.data?.meta}
        isLoading={tours.isPending}
        errorMessage={tours.isError ? errorMessage(tours.error) : null}
        onRetry={() => void tours.refetch()}
        onPageChange={setPage}
        onPerPageChange={(size) => { setPerPage(size); setPage(1) }}
        emptyMessage="Aucune tournée ne correspond."
      />
    </div>
  )
}

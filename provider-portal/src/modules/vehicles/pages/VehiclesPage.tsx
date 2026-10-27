import { Plus } from 'lucide-react'
import { useState } from 'react'

import { Can } from '@/app/guards/Can'
import { errorMessage } from '@/shared/api/errors'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { StatusFilter } from '@/shared/components/data/StatusFilter'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { VehicleDialog } from '../components/VehicleDialog'
import { VehicleRowActions } from '../components/VehicleRowActions'
import { useVehicles } from '../hooks/useVehicles'
import type { Vehicle } from '../types'

const STATUSES = [
  { value: 'active', label: 'Actif' },
  { value: 'inactive', label: 'Inactif' },
  { value: 'maintenance', label: 'En maintenance' },
]

const number = (value: string) => Number(value).toLocaleString('fr-CH')

const columns: Column<Vehicle>[] = [
  { key: 'code', header: 'Code', cell: (vehicle) => <span className="font-medium">{vehicle.code}</span> },
  { key: 'registration', header: 'Immatriculation', cell: (vehicle) => vehicle.registrationNumber },
  { key: 'type', header: 'Type', hideOnMobile: true, cell: (vehicle) => vehicle.vehicleType?.name ?? '—' },
  {
    key: 'capacity',
    header: 'Capacités',
    hideOnMobile: true,
    cell: (vehicle) => `${number(vehicle.payloadCapacity)} kg · ${number(vehicle.volumeCapacity)} m³ · ${vehicle.palletCapacity} pal.`,
  },
  { key: 'status', header: 'Statut', cell: (vehicle) => <StatusBadge code={vehicle.status} label={vehicle.statusLabel} /> },
]

/** Les véhicules du fournisseur : liste filtrée côté serveur, ajout, actions de ligne. */
export function VehiclesPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState<number | undefined>(undefined)
  const [adding, setAdding] = useState(false)
  const vehicles = useVehicles({ search, status, page, perPage })

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Véhicules"
        description="Les véhicules de votre entreprise et leurs capacités."
        actions={
          <Can permission="provider.vehicles.create">
            <Button onClick={() => setAdding(true)}>
              <Plus className="size-4" aria-hidden /> Ajouter un véhicule
            </Button>
          </Can>
        }
      />
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <SearchInput value={search} onChange={(value) => { setSearch(value); setPage(1) }} label="Rechercher un véhicule" placeholder="Code ou immatriculation" />
        <StatusFilter value={status} onChange={(value) => { setStatus(value); setPage(1) }} options={STATUSES} />
      </div>
      <DataTable
        columns={columns}
        rows={vehicles.data?.data ?? []}
        rowKey={(vehicle) => vehicle.id}
        meta={vehicles.data?.meta}
        isLoading={vehicles.isPending}
        errorMessage={vehicles.isError ? errorMessage(vehicles.error) : null}
        onRetry={() => void vehicles.refetch()}
        onPageChange={setPage}
        onPerPageChange={(size) => { setPerPage(size); setPage(1) }}
        emptyMessage="Aucun véhicule ne correspond."
        actions={(vehicle) => <VehicleRowActions vehicle={vehicle} />}
      />
      <VehicleDialog open={adding} onOpenChange={setAdding} />
    </div>
  )
}

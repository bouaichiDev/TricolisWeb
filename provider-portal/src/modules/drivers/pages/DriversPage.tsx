import { UserPlus } from 'lucide-react'
import { useState } from 'react'

import { Can } from '@/app/guards/Can'
import { errorMessage } from '@/shared/api/errors'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { StatusFilter } from '@/shared/components/data/StatusFilter'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { DriverDialog } from '../components/DriverDialog'
import { DriverRowActions } from '../components/DriverRowActions'
import { useDrivers } from '../hooks/useDrivers'
import type { Driver } from '../types'

const STATUSES = [
  { value: 'active', label: 'Actif' },
  { value: 'inactive', label: 'Inactif' },
]

const columns: Column<Driver>[] = [
  { key: 'code', header: 'Code', cell: (driver) => <span className="font-medium">{driver.code}</span> },
  { key: 'name', header: 'Nom', cell: (driver) => driver.name },
  { key: 'email', header: 'E-mail', hideOnMobile: true, cell: (driver) => driver.email ?? '—' },
  { key: 'phone', header: 'Téléphone', hideOnMobile: true, cell: (driver) => driver.phone ?? '—' },
  { key: 'status', header: 'Statut', cell: (driver) => <StatusBadge code={driver.status} label={driver.statusLabel} /> },
]

/** Les chauffeurs du fournisseur : liste filtrée côté serveur, ajout, actions de ligne. */
export function DriversPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState<number | undefined>(undefined)
  const [adding, setAdding] = useState(false)
  const drivers = useDrivers({ search, status, page, perPage })

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Chauffeurs"
        description="Les chauffeurs de votre entreprise, que vous affectez aux tournées."
        actions={
          <Can permission="provider.drivers.create">
            <Button onClick={() => setAdding(true)}>
              <UserPlus className="size-4" aria-hidden /> Ajouter un chauffeur
            </Button>
          </Can>
        }
      />
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <SearchInput value={search} onChange={(value) => { setSearch(value); setPage(1) }} label="Rechercher un chauffeur" placeholder="Code, nom ou e-mail" />
        <StatusFilter value={status} onChange={(value) => { setStatus(value); setPage(1) }} options={STATUSES} />
      </div>
      <DataTable
        columns={columns}
        rows={drivers.data?.data ?? []}
        rowKey={(driver) => driver.id}
        meta={drivers.data?.meta}
        isLoading={drivers.isPending}
        errorMessage={drivers.isError ? errorMessage(drivers.error) : null}
        onRetry={() => void drivers.refetch()}
        onPageChange={setPage}
        onPerPageChange={(size) => { setPerPage(size); setPage(1) }}
        emptyMessage="Aucun chauffeur ne correspond."
        actions={(driver) => <DriverRowActions driver={driver} />}
      />
      <DriverDialog open={adding} onOpenChange={setAdding} />
    </div>
  )
}

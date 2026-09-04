import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { useDriverList } from '@/modules/drivers/hooks/useDrivers'
import { useVehicleList } from '@/modules/vehicles/hooks/useVehicles'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

interface Row {
  id: string
  code: string
  label: string
  status: string
}

interface ProviderResourcesTabProps {
  providerId: string
  kind: 'drivers' | 'vehicles'
  active: boolean
}

/**
 * Chauffeurs ou véhicules d'un fournisseur.
 *
 * Les deux onglets montrent la même chose — un code, un nom, un statut — et
 * mènent à la fiche complète. Les dédoubler produirait deux fois le même
 * tableau.
 *
 * « Ajouter » emporte le fournisseur dans l'URL : on vient de sa fiche, le
 * redemander serait absurde.
 */
export function ProviderResourcesTab({ providerId, kind, active }: ProviderResourcesTabProps) {
  const { t } = useTranslation()

  const filters = { page: 1, perPage: 25, providerId }
  const drivers = useDriverList(filters, active && kind === 'drivers')
  const vehicles = useVehicleList(filters, active && kind === 'vehicles')

  const query = kind === 'drivers' ? drivers : vehicles

  const rows: Row[] =
    kind === 'drivers'
      ? (drivers.data?.data ?? []).map((driver) => ({
          id: driver.id,
          code: driver.code,
          label: driver.name,
          status: driver.status,
        }))
      : (vehicles.data?.data ?? []).map((vehicle) => ({
          id: vehicle.id,
          code: vehicle.code,
          label: vehicle.registrationNumber,
          status: vehicle.status,
        }))

  const source = kind === 'drivers' ? 'driver' : 'vehicle'

  const columns: Column<Row>[] = [
    {
      key: 'code',
      header: t(`${kind}.fields.code`),
      cell: (row) => (
        <Link to={`/${kind}/${row.id}`} className="font-medium text-primary hover:underline">
          {row.code}
        </Link>
      ),
    },
    {
      key: 'label',
      header: t(kind === 'drivers' ? 'drivers.fields.name' : 'vehicles.fields.registrationNumber'),
      cell: (row) => row.label,
    },
    {
      key: 'status',
      header: t(`${kind}.fields.status`),
      cell: (row) => <StatusBadge status={row.status} source={source} />,
    },
  ]

  return (
    <SectionCard
      title={t(`${kind}.title`)}
      actions={
        <PermissionGuard permission={`${kind}.create`}>
          <Button asChild size="sm">
            <Link to={`/${kind}/create?providerId=${providerId}`}>
              <Plus className="size-4" aria-hidden />
              {t(`${kind}.create`)}
            </Link>
          </Button>
        </PermissionGuard>
      }
    >
      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(row) => row.id}
        meta={query.data?.meta}
        isLoading={query.isPending}
        error={query.error}
        onRetry={() => void query.refetch()}
        emptyMessage={t(`${kind}.empty`)}
      />
    </SectionCard>
  )
}

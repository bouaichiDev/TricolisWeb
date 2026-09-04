import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { OrganizationAvatar } from '../components/OrganizationAvatar'
import { useOrganizationList } from '../hooks/useOrganizations'
import type { Organization, OrganizationFilters } from '../types/organization'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

/**
 * Liste des organisations.
 *
 * L'API ne renvoie que celles dont l'utilisateur connecté est membre : il n'y a
 * donc pas d'annuaire global à afficher, et aucun filtre ne peut élargir cette
 * portée depuis le frontend.
 */
export function OrganizationListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [filters, setFilters] = useState<OrganizationFilters>({
    page: 1,
    perPage: 25,
    sort: 'name',
    direction: 'asc',
  })

  const { data, isPending, error, refetch } = useOrganizationList(filters)

  const columns: Column<Organization>[] = [
    {
      // Sans en-tête : une colonne d'images n'a rien à annoncer, et « Logo »
      // au-dessus de neuf pixels d'icône pèserait plus que ce qu'il nomme.
      key: 'logo',
      header: '',
      cell: (row) => (
        <OrganizationAvatar organizationId={row.id} hasLogo={row.hasLogo} name={row.name} />
      ),
    },
    {
      key: 'code',
      header: t('organizations.fields.code'),
      sortKey: 'code',
      cell: (row) => (
        <Link
          to={`/organizations/${row.id}`}
          className="font-medium text-primary hover:underline"
          onClick={(event) => event.stopPropagation()}
        >
          {row.code}
        </Link>
      ),
    },
    {
      key: 'name',
      header: t('organizations.fields.name'),
      sortKey: 'name',
      cell: (row) => row.name,
    },
    {
      key: 'email',
      header: t('organizations.fields.email'),
      hideOnMobile: true,
      cell: (row) => row.email ?? <span className="text-muted-foreground">—</span>,
    },
    {
      key: 'status',
      header: t('organizations.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('organizations.title')}
        description={t('organizations.subtitle')}
        actions={
          <PermissionGuard permission="organizations.create">
            <Button asChild>
              <Link to="/organizations/create">
                <Plus className="size-4" aria-hidden />
                {t('organizations.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <SearchInput
        value={filters.search ?? ''}
        onChange={(search) =>
          setFilters((current) => ({ ...current, page: 1, search: search || undefined }))
        }
      />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        sort={filters.sort}
        direction={filters.direction}
        onSortChange={(sortKey) =>
          setFilters((current) => ({
            ...current,
            sort: sortKey,
            direction: current.sort === sortKey && current.direction === 'asc' ? 'desc' : 'asc',
          }))
        }
        onPageChange={(page) => setFilters((current) => ({ ...current, page }))}
        onRetry={() => void refetch()}
        onRowClick={(row) => void navigate(`/organizations/${row.id}`)}
      />
    </div>
  )
}

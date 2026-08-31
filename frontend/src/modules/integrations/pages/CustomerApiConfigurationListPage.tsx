import { KeyRound, Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { CustomerFilterSelect } from '@/modules/customers/components/CustomerFilterSelect'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { formatDateTime } from '@/shared/utils/format'

import { useCustomerApiConfigurations } from '../hooks/useCustomerApiConfigurations'
import type {
  CustomerApiConfiguration,
  CustomerApiConfigurationFilters,
} from '../types/customerIntegration'

/**
 * Accès API des clients.
 *
 * **Aucune clé, aucun hash.** La ressource n'expose pas `apiKeyHash`, et
 * l'écran n'en affiche donc rien — pas même masqué. Ce qui se lit ici, c'est
 * l'existence d'un accès, ses restrictions et sa dernière utilisation.
 *
 * Les IP et les permissions sont résumées : dix lignes de CIDR par ligne de
 * tableau rendraient la liste illisible, alors que ce qu'on y cherche est
 * « combien » et « pour qui ».
 */
export function CustomerApiConfigurationListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [filters, setFilters] = useState<CustomerApiConfigurationFilters>({
    page: 1,
    perPage: 25,
    sort: 'name',
    direction: 'asc',
  })

  const { data, isPending, error, refetch } = useCustomerApiConfigurations(filters)

  const patch = (next: Partial<CustomerApiConfigurationFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const columns: Column<CustomerApiConfiguration>[] = [
    {
      key: 'name',
      header: t('integrations.fields.name'),
      sortKey: 'name',
      cell: (row) => (
        <span className="flex items-center gap-2 font-medium">
          <KeyRound className="size-4 text-muted-foreground" aria-hidden />
          {row.name}
        </span>
      ),
    },
    {
      key: 'allowedIps',
      header: t('integrations.api.allowedIps'),
      hideOnMobile: true,
      cell: (row) =>
        row.allowedIps === null || row.allowedIps.length === 0 ? (
          <span className="text-muted-foreground">{t('integrations.api.anyIp')}</span>
        ) : (
          t('integrations.api.ipCount', { count: row.allowedIps.length })
        ),
    },
    {
      key: 'permissions',
      header: t('integrations.api.permissions'),
      hideOnMobile: true,
      cell: (row) =>
        row.permissions === null || row.permissions.length === 0 ? (
          <span className="text-muted-foreground">{t('integrations.api.noPermission')}</span>
        ) : (
          t('integrations.api.permissionsCount', { count: row.permissions.length })
        ),
    },
    {
      key: 'isActive',
      header: t('integrations.fields.isActive'),
      sortKey: 'is_active',
      cell: (row) => (
        <Badge variant={row.isActive ? 'outline' : 'secondary'}>
          {row.isActive ? t('common.active') : t('common.inactive')}
        </Badge>
      ),
    },
    {
      key: 'lastUsedAt',
      header: t('integrations.api.lastUsedAt'),
      sortKey: 'last_used_at',
      cell: (row) =>
        row.lastUsedAt === null ? (
          <span className="text-muted-foreground">{t('integrations.api.neverUsed')}</span>
        ) : (
          formatDateTime(row.lastUsedAt)
        ),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('integrations.api.title')}
        description={t('integrations.api.subtitle')}
        actions={
          <PermissionGuard permission="customer_api_configurations.create">
            <Button asChild>
              <Link to="/integrations/api-access/create">
                <Plus className="size-4" aria-hidden />
                {t('integrations.api.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row">
        <SearchInput
          value={filters.search ?? ''}
          onChange={(value) => patch({ search: value === '' ? undefined : value })}
          placeholder={t('integrations.api.searchPlaceholder')}
        />
        <CustomerFilterSelect
          value={filters.customerId}
          onChange={(customerId) => patch({ customerId })}
        />
      </div>

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
        onRowClick={(row) => void navigate(`/integrations/api-access/${row.id}`)}
        emptyMessage={t('integrations.api.empty')}
      />
    </div>
  )
}

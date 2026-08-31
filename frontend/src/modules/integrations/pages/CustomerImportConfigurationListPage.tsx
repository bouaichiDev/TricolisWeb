import { Plus } from 'lucide-react'
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

import { useCustomerImportConfigurations } from '../hooks/useCustomerImportConfigurations'
import type {
  CustomerImportConfiguration,
  CustomerImportConfigurationFilters,
} from '../types/customerIntegration'

/**
 * Configurations d'import des clients.
 *
 * **Ce n'est pas un écran d'imports.** Il n'existe aucune table `Import`, aucun
 * historique de lecture, aucune ligne en erreur : le modèle officiel s'arrête à
 * la configuration, et le §5 interdit d'en afficher davantage. Ce qu'on voit
 * ici, c'est la manière dont un fichier serait lu, pas ce qui a été lu.
 *
 * `sourceType` et `fileFormat` sont affichés bruts : ce sont des chaînes
 * libres, sans référentiel qui pourrait en donner un libellé.
 */
export function CustomerImportConfigurationListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [filters, setFilters] = useState<CustomerImportConfigurationFilters>({
    page: 1,
    perPage: 25,
    sort: 'name',
    direction: 'asc',
  })

  const { data, isPending, error, refetch } = useCustomerImportConfigurations(filters)

  const patch = (next: Partial<CustomerImportConfigurationFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const columns: Column<CustomerImportConfiguration>[] = [
    {
      key: 'name',
      header: t('integrations.fields.name'),
      sortKey: 'name',
      cell: (row) => <span className="font-medium">{row.name}</span>,
    },
    {
      key: 'sourceType',
      header: t('integrations.fields.sourceType'),
      sortKey: 'source_type',
      cell: (row) => <span className="font-mono text-xs">{row.sourceType}</span>,
    },
    {
      key: 'fileFormat',
      header: t('integrations.fields.fileFormat'),
      sortKey: 'file_format',
      cell: (row) => <span className="font-mono text-xs">{row.fileFormat}</span>,
    },
    {
      key: 'mapping',
      header: t('integrations.fields.mapping'),
      hideOnMobile: true,
      cell: (row) =>
        row.mapping === null ? (
          <span className="text-muted-foreground">—</span>
        ) : (
          t('integrations.imports.mappedFields', {
            count: Object.keys(row.mapping).length,
          })
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
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('integrations.imports.title')}
        description={t('integrations.imports.subtitle')}
        actions={
          <PermissionGuard permission="customer_import_configurations.create">
            <Button asChild>
              <Link to="/integrations/imports/create">
                <Plus className="size-4" aria-hidden />
                {t('integrations.imports.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row">
        <SearchInput
          value={filters.search ?? ''}
          onChange={(value) => patch({ search: value === '' ? undefined : value })}
          placeholder={t('integrations.imports.searchPlaceholder')}
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
        onRowClick={(row) => void navigate(`/integrations/imports/${row.id}`)}
        emptyMessage={t('integrations.imports.empty')}
      />
    </div>
  )
}

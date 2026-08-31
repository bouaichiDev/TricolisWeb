import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { CustomerFilterSelect } from '@/modules/customers/components/CustomerFilterSelect'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Badge } from '@/shared/components/ui/badge'

import { useExportConfigurationList } from '../hooks/useExports'
import type { ExportConfiguration, ExportConfigurationFilters } from '../types/export'

/**
 * Toutes les destinations d'export, tous clients confondus.
 *
 * L'écran de Facturation montre les destinations **d'un** client, parce que
 * c'est là qu'on les règle. Celui-ci répond à l'autre question — « qui reçoit
 * quoi, et par quel canal » — que la Phase 6 ne pouvait pas poser faute de route
 * globale.
 *
 * **Les deux lisent les mêmes configurations.** Le §34 interdit une table
 * séparée pour les factures, et le §77 un second module : les configurations
 * `invoice` de la Phase 6 apparaissent donc ici, sans duplication.
 *
 * **Aucun mot de passe.** Le serveur ne rend que `hasPassword` ; la colonne dit
 * qu'un secret est posé, jamais lequel (§45).
 */
export function ExportConfigurationDirectoryPage() {
  const { t } = useTranslation()

  const [filters, setFilters] = useState<ExportConfigurationFilters>({
    page: 1,
    perPage: 25,
    sort: 'name',
    direction: 'asc',
  })

  const { data, isPending, error, refetch } = useExportConfigurationList(filters)

  const patch = (next: Partial<ExportConfigurationFilters>) =>
    setFilters((current) => ({ ...current, page: 1, ...next }))

  const columns: Column<ExportConfiguration>[] = [
    {
      key: 'name',
      header: t('exports.configurations.fields.name'),
      sortKey: 'name',
      cell: (row) => <span className="font-medium">{row.name}</span>,
    },
    {
      key: 'exportType',
      header: t('integrations.exports.exportType'),
      sortKey: 'export_type',
      cell: (row) => <span className="font-mono text-xs">{row.exportType}</span>,
    },
    {
      key: 'channel',
      header: t('exports.configurations.fields.transport'),
      sortKey: 'transport',
      cell: (row) => (
        <span className="flex gap-1">
          <Badge variant="outline">
            {t(`exports.transports.${row.transport}`, { defaultValue: row.transport })}
          </Badge>
          <Badge variant="outline">{row.format.toUpperCase()}</Badge>
        </span>
      ),
    },
    {
      key: 'host',
      header: t('exports.configurations.fields.host'),
      hideOnMobile: true,
      cell: (row) =>
        row.host === null ? <span className="text-muted-foreground">—</span> : row.host,
    },
    {
      key: 'frequency',
      header: t('integrations.exports.frequency'),
      sortKey: 'frequency',
      hideOnMobile: true,
      cell: (row) =>
        row.frequency === null ? (
          <span className="text-muted-foreground">—</span>
        ) : (
          <span className="font-mono text-xs">{row.frequency}</span>
        ),
    },
    {
      key: 'secret',
      header: t('integrations.exports.secret'),
      hideOnMobile: true,
      cell: (row) => (
        <span className="text-sm text-muted-foreground">
          {row.hasPassword ? t('integrations.exports.secretSet') : t('integrations.exports.noSecret')}
        </span>
      ),
    },
    {
      key: 'isActive',
      header: t('exports.configurations.fields.isActive'),
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
        title={t('integrations.exports.title')}
        description={t('integrations.exports.subtitle')}
      />

      <div className="flex flex-col gap-3 sm:flex-row">
        <SearchInput
          value={filters.search ?? ''}
          onChange={(value) => patch({ search: value === '' ? undefined : value })}
          placeholder={t('integrations.exports.searchPlaceholder')}
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
        emptyMessage={t('integrations.exports.empty')}
      />

      <p className="text-xs text-muted-foreground">{t('integrations.exports.editHint')}</p>
    </div>
  )
}

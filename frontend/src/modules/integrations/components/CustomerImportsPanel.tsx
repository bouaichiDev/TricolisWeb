import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { Badge } from '@/shared/components/ui/badge'

import { useCustomerImportConfigurationsOf } from '../hooks/useCustomerImportConfigurations'
import type { CustomerImportConfiguration } from '../types/customerIntegration'

/**
 * Les configurations d'import de ce client, et d'aucun autre.
 *
 * La route imbriquée `customers/{c}/import-configurations` est employée plutôt
 * qu'un filtre sur la liste globale : c'est le serveur qui restreint, et le §7
 * exige qu'aucune configuration d'un client ne soit visible depuis un autre.
 */
export function CustomerImportsPanel({ customerId }: { customerId: string }) {
  const { t } = useTranslation()

  const { data, isPending, error, refetch } = useCustomerImportConfigurationsOf(customerId, {
    page: 1,
    perPage: 25,
    sort: 'name',
    direction: 'asc',
  })

  const columns: Column<CustomerImportConfiguration>[] = [
    {
      key: 'name',
      header: t('integrations.fields.name'),
      cell: (row) => (
        <Link to={`/integrations/imports/${row.id}`} className="font-medium hover:underline">
          {row.name}
        </Link>
      ),
    },
    {
      key: 'sourceType',
      header: t('integrations.fields.sourceType'),
      cell: (row) => <span className="font-mono text-xs">{row.sourceType}</span>,
    },
    {
      key: 'fileFormat',
      header: t('integrations.fields.fileFormat'),
      cell: (row) => <span className="font-mono text-xs">{row.fileFormat}</span>,
    },
    {
      key: 'isActive',
      header: t('integrations.fields.isActive'),
      cell: (row) => (
        <Badge variant={row.isActive ? 'outline' : 'secondary'}>
          {row.isActive ? t('common.active') : t('common.inactive')}
        </Badge>
      ),
    },
  ]

  return (
    <DataTable
      columns={columns}
      rows={data?.data ?? []}
      rowKey={(row) => row.id}
      isLoading={isPending}
      error={error}
      onRetry={() => void refetch()}
      emptyMessage={t('integrations.imports.emptyForCustomer')}
    />
  )
}

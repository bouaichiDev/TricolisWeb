import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { Badge } from '@/shared/components/ui/badge'
import { formatDateTime } from '@/shared/utils/format'

import { useCustomerApiConfigurationsOf } from '../hooks/useCustomerApiConfigurations'
import type { CustomerApiConfiguration } from '../types/customerIntegration'

/**
 * Les accès API de ce client.
 *
 * **Aucune clé, aucun hash.** Ce qui se lit ici, c'est qu'un accès existe, ce
 * qu'il peut faire, d'où il peut venir, et quand il a servi pour la dernière
 * fois. La clé elle-même n'a été visible qu'une fois, à sa création.
 *
 * Les actions — modifier, renouveler, supprimer — vivent sur la fiche : elles
 * demandent une confirmation que ce tableau ne peut pas porter.
 */
export function CustomerApiAccessPanel({ customerId }: { customerId: string }) {
  const { t } = useTranslation()

  const { data, isPending, error, refetch } = useCustomerApiConfigurationsOf(customerId, {
    page: 1,
    perPage: 25,
    sort: 'name',
    direction: 'asc',
  })

  const columns: Column<CustomerApiConfiguration>[] = [
    {
      key: 'name',
      header: t('integrations.fields.name'),
      cell: (row) => (
        <Link to={`/integrations/api-access/${row.id}`} className="font-medium hover:underline">
          {row.name}
        </Link>
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
      cell: (row) => (
        <Badge variant={row.isActive ? 'outline' : 'secondary'}>
          {row.isActive ? t('common.active') : t('common.inactive')}
        </Badge>
      ),
    },
    {
      key: 'lastUsedAt',
      header: t('integrations.api.lastUsedAt'),
      cell: (row) =>
        row.lastUsedAt === null ? (
          <span className="text-muted-foreground">{t('integrations.api.neverUsed')}</span>
        ) : (
          formatDateTime(row.lastUsedAt)
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
      emptyMessage={t('integrations.api.emptyForCustomer')}
    />
  )
}

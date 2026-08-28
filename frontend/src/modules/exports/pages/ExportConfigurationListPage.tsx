import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ExportConfigurationDialog } from '../components/ExportConfigurationDialog'
import {
  useCreateExportConfiguration,
  useDeleteExportConfiguration,
  useExportConfigurations,
  useUpdateExportConfiguration,
} from '../hooks/useExports'
import type { ExportConfiguration } from '../types/export'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { useCustomerList } from '@/modules/customers/hooks/useCustomers'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

/**
 * Où partent les factures d'un client.
 *
 * **Client par client**, parce que c'est ainsi que l'API les expose et que le
 * §113 l'exige : la configuration d'un client ne doit jamais servir à un autre.
 * Tant qu'aucun client n'est choisi, l'écran le dit plutôt que d'afficher une
 * table vide.
 */
export function ExportConfigurationListPage() {
  const { t } = useTranslation()
  const [customerId, setCustomerId] = useState('')
  const [editing, setEditing] = useState<ExportConfiguration | null>(null)
  const [creating, setCreating] = useState(false)
  const [toDelete, setToDelete] = useState<ExportConfiguration | null>(null)

  const customers = useCustomerList({ page: 1, perPage: 100 })
  const { data, isPending, error, refetch } = useExportConfigurations(customerId)
  const create = useCreateExportConfiguration(customerId)
  const update = useUpdateExportConfiguration(customerId)
  const remove = useDeleteExportConfiguration(customerId)

  const columns: Column<ExportConfiguration>[] = [
    { key: 'name', header: t('exports.configurations.fields.name'), cell: (row) => row.name },
    {
      key: 'transport',
      header: t('exports.configurations.fields.transport'),
      cell: (row) => (
        <span className="flex gap-1">
          <Badge variant="outline">{t(`exports.transports.${row.transport}`, row.transport)}</Badge>
          <Badge variant="outline">{row.format.toUpperCase()}</Badge>
        </span>
      ),
    },
    { key: 'host', header: t('exports.configurations.fields.host'), cell: (row) => row.host ?? '' },
    {
      key: 'isActive',
      header: t('exports.configurations.fields.isActive'),
      cell: (row) => (
        <Badge variant={row.isActive ? 'default' : 'secondary'}>
          {row.isActive ? t('common.yes') : t('common.no')}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: '',
      className: 'w-24',
      cell: (row) => (
        <span className="flex gap-1">
          <PermissionGuard permission="customer_export_configurations.update">
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.edit')}
              onClick={() => setEditing(row)}
            >
              <Pencil className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
          <PermissionGuard permission="customer_export_configurations.delete">
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.delete')}
              onClick={() => setToDelete(row)}
            >
              <Trash2 className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
        </span>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('exports.configurations.title')}
        description={t('exports.configurations.subtitle')}
        actions={
          customerId ? (
            <PermissionGuard permission="customer_export_configurations.create">
              <Button onClick={() => setCreating(true)}>
                <Plus className="size-4" aria-hidden />
                {t('exports.configurations.create')}
              </Button>
            </PermissionGuard>
          ) : null
        }
      />

      <div className="sm:w-72">
        <AsyncSelect
          label={t('exports.configurations.fields.customer')}
          value={customerId}
          onChange={setCustomerId}
          options={(customers.data?.data ?? []).map((customer) => ({
            value: customer.id,
            label: customer.name,
            hint: customer.code,
          }))}
          isLoading={customers.isPending}
        />
      </div>

      {customerId === '' ? (
        <EmptyState
          title={t('exports.configurations.chooseCustomerTitle')}
          description={t('exports.configurations.chooseCustomer')}
        />
      ) : (
        <DataTable
          columns={columns}
          rows={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isPending}
          error={error}
          onRetry={() => void refetch()}
          emptyMessage={t('exports.configurations.empty')}
        />
      )}

      {creating ? (
        <ExportConfigurationDialog
          customerId={customerId}
          configuration={null}
          open
          onOpenChange={setCreating}
          isPending={create.isPending}
          onSubmit={(payload) => create.mutate(payload, { onSuccess: () => setCreating(false) })}
        />
      ) : null}

      {editing ? (
        <ExportConfigurationDialog
          customerId={customerId}
          configuration={editing}
          open
          onOpenChange={(open) => !open && setEditing(null)}
          isPending={update.isPending}
          onSubmit={(payload) =>
            update.mutate(
              { id: editing.id, payload },
              { onSuccess: () => setEditing(null) },
            )
          }
        />
      ) : null}

      <ConfirmDialog
        open={toDelete !== null}
        onOpenChange={(open) => !open && setToDelete(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: toDelete?.name ?? '' })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (toDelete === null) return
          remove.mutate(toDelete.id, { onSuccess: () => setToDelete(null) })
        }}
      />
    </div>
  )
}

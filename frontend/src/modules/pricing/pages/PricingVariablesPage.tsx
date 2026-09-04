import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PricingVariableDialog } from '../components/PricingVariableDialog'
import { useDeletePricingVariable, usePricingVariables } from '../hooks/usePricing'
import type { PricingVariable } from '../types/pricing'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

/**
 * Le catalogue des variables tarifaires, tenu par la plateforme.
 *
 * **C'est ici que se décide ce qu'un organisme peut écrire dans une formule.**
 * Laisser chacun inventer ses variables ferait qu'une même formule ne voudrait
 * plus dire la même chose d'un organisme à l'autre, et ouvrirait le choix de la
 * source — c'est-à-dire des colonnes de la base.
 *
 * La source est montrée en clair, table et colonne : celui qui expose une
 * variable doit voir ce qu'elle va chercher, et celui qui l'emploie aussi.
 */
export function PricingVariablesPage() {
  const { t } = useTranslation()
  const { data, isPending, error, refetch } = usePricingVariables()
  const remove = useDeletePricingVariable()

  const [editing, setEditing] = useState<PricingVariable | null>(null)
  const [creating, setCreating] = useState(false)
  const [toDelete, setToDelete] = useState<PricingVariable | null>(null)

  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />

  const columns: Column<PricingVariable>[] = [
    {
      key: 'code',
      header: t('pricing.variables.fields.code'),
      cell: (row) => (
        <span className="flex flex-col">
          <code className="font-medium">{`{P:${row.code}}`}</code>
          <span className="text-xs text-muted-foreground">{row.label}</span>
        </span>
      ),
    },
    {
      key: 'kind',
      header: t('pricing.variables.fields.kind'),
      cell: (row) => (
        <Badge variant={row.kind === 'numeric' ? 'default' : 'outline'}>
          {t(`pricing.variables.kinds.${row.kind}`)}
        </Badge>
      ),
    },
    {
      key: 'source',
      header: t('pricing.variables.fields.source'),
      cell: (row) => (
        <span className="flex flex-col">
          <code className="text-xs">{`${row.sourceTable}.${row.sourceColumn}`}</code>
          <span className="text-xs text-muted-foreground">{row.description}</span>
        </span>
      ),
    },
    {
      key: 'unit',
      header: t('pricing.variables.fields.unit'),
      cell: (row) => row.unit ?? '',
    },
    {
      key: 'isActive',
      header: t('pricing.variables.fields.isActive'),
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
        <PermissionGuard permission="pricing_variables.manage">
          <span className="flex gap-1">
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.edit')}
              onClick={() => setEditing(row)}
            >
              <Pencil className="size-4" aria-hidden />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.delete')}
              onClick={() => setToDelete(row)}
            >
              <Trash2 className="size-4" aria-hidden />
            </Button>
          </span>
        </PermissionGuard>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('pricing.variables.title')}
        description={t('pricing.variables.subtitle')}
        actions={
          <PermissionGuard permission="pricing_variables.manage">
            <Button onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('pricing.variables.create')}
            </Button>
          </PermissionGuard>
        }
      />

      <DataTable
        columns={columns}
        rows={data ?? []}
        rowKey={(row) => row.id}
        isLoading={isPending}
        emptyMessage={t('pricing.variables.empty')}
      />

      {creating || editing ? (
        <PricingVariableDialog
          variable={editing}
          open
          onOpenChange={(open) => {
            if (open) return
            setCreating(false)
            setEditing(null)
          }}
        />
      ) : null}

      <ConfirmDialog
        open={toDelete !== null}
        onOpenChange={(open) => !open && setToDelete(null)}
        title={t('confirm.deleteTitle')}
        description={t('pricing.variables.confirmDelete', { code: toDelete?.code ?? '' })}
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

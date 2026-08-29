import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router-dom'

import { PriceMatrixDialog } from '../components/PriceMatrixDialog'
import { PriceRuleDialog } from '../components/PriceRuleDialog'
import { useDeleteMatrix, useDeleteRule, usePriceList } from '../hooks/usePricing'
import type { PriceMatrix, PriceRule } from '../types/pricing'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

/**
 * Un barème : ses règles, ses matrices.
 *
 * Les deux se lisent ensemble parce qu'elles se répondent — une matrice
 * désigne des règles, et une règle citée par une matrice ne s'applique que
 * dans ses zones. Les séparer en deux écrans obligerait à faire l'aller-retour
 * pour comprendre un tarif.
 */
export function PriceListDetailPage() {
  const { t } = useTranslation()
  const { id = '' } = useParams<{ id: string }>()

  const { data: priceList, isPending, error, refetch } = usePriceList(id)
  const removeRule = useDeleteRule()
  const removeMatrix = useDeleteMatrix()

  const [editingRule, setEditingRule] = useState<PriceRule | null>(null)
  const [creatingRule, setCreatingRule] = useState(false)
  const [editingMatrix, setEditingMatrix] = useState<PriceMatrix | null>(null)
  const [creatingMatrix, setCreatingMatrix] = useState(false)
  const [ruleToDelete, setRuleToDelete] = useState<PriceRule | null>(null)
  const [matrixToDelete, setMatrixToDelete] = useState<PriceMatrix | null>(null)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!priceList) return null

  const ruleColumns: Column<PriceRule>[] = [
    {
      key: 'code',
      header: t('pricing.rules.fields.code'),
      cell: (row) => (
        <span className="flex flex-col">
          <span className="font-medium">{row.code}</span>
          <span className="text-xs text-muted-foreground">{row.name}</span>
        </span>
      ),
    },
    {
      key: 'service',
      header: t('pricing.rules.fields.service'),
      cell: (row) => row.serviceName ?? t('pricing.rules.anyService'),
    },
    {
      key: 'formula',
      header: t('pricing.rules.fields.formula'),
      cell: (row) => <code className="text-xs">{row.formula}</code>,
    },
    {
      key: 'conditions',
      header: t('pricing.rules.conditions'),
      cell: (row) =>
        (row.conditions ?? []).length === 0 ? (
          <span className="text-xs text-muted-foreground">{t('pricing.rules.noCondition')}</span>
        ) : (
          <span className="flex flex-col gap-0.5">
            {(row.conditions ?? []).map((condition) => (
              <span key={condition.id} className="text-xs">
                {`${condition.variable} ${condition.operator} ${condition.valueFrom ?? ''}`}
                {condition.valueTo ? ` – ${condition.valueTo}` : ''}
              </span>
            ))}
          </span>
        ),
    },
    {
      key: 'scope',
      header: '',
      cell: (row) =>
        row.matrixDriven ? (
          <Badge variant="outline">{t('pricing.rules.matrixDriven')}</Badge>
        ) : null,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-24',
      cell: (row) => (
        <PermissionGuard permission="price_lists.update">
          <span className="flex gap-1">
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.edit')}
              onClick={() => setEditingRule(row)}
            >
              <Pencil className="size-4" aria-hidden />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.delete')}
              onClick={() => setRuleToDelete(row)}
            >
              <Trash2 className="size-4" aria-hidden />
            </Button>
          </span>
        </PermissionGuard>
      ),
    },
  ]

  const matrixColumns: Column<PriceMatrix>[] = [
    {
      key: 'code',
      header: t('pricing.matrices.fields.code'),
      cell: (row) => (
        <span className="flex flex-col">
          <span className="font-medium">{row.code}</span>
          <span className="text-xs text-muted-foreground">{row.name}</span>
        </span>
      ),
    },
    {
      key: 'zones',
      header: t('pricing.matrices.zones'),
      cell: (row) => (
        <span className="flex flex-col gap-0.5">
          {(row.rows ?? []).map((zone) => (
            <span key={zone.id} className="text-xs">
              {`${zone.label} : ${zone.rangeFrom}${zone.rangeTo ? ` – ${zone.rangeTo}` : '+'} → ${zone.priceRuleCode ?? ''}`}
            </span>
          ))}
        </span>
      ),
    },
    {
      key: 'actions',
      header: '',
      className: 'w-24',
      cell: (row) => (
        <PermissionGuard permission="price_lists.update">
          <span className="flex gap-1">
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.edit')}
              onClick={() => setEditingMatrix(row)}
            >
              <Pencil className="size-4" aria-hidden />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('common.delete')}
              onClick={() => setMatrixToDelete(row)}
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
        title={priceList.name}
        description={`${priceList.code} · ${t(`pricing.scopes.${priceList.scope}`)}`}
        actions={
          priceList.scope === 'customer' && priceList.customers?.length ? (
            <span className="flex flex-wrap gap-1">
              {priceList.customers.map((customer) => (
                <Badge key={customer.id} variant="secondary">
                  {customer.name}
                </Badge>
              ))}
            </span>
          ) : null
        }
      />

      <SectionCard
        title={t('pricing.rules.title')}
        description={t('pricing.rules.sectionHint')}
        actions={
          <PermissionGuard permission="price_lists.update">
            <Button size="sm" onClick={() => setCreatingRule(true)}>
              <Plus className="size-4" aria-hidden />
              {t('pricing.rules.create')}
            </Button>
          </PermissionGuard>
        }
      >
        <DataTable
          columns={ruleColumns}
          rows={priceList.rules ?? []}
          rowKey={(row) => row.id}
          emptyMessage={t('pricing.rules.empty')}
        />
      </SectionCard>

      <SectionCard
        title={t('pricing.matrices.title')}
        description={t('pricing.matrices.sectionHint')}
        actions={
          <PermissionGuard permission="price_lists.update">
            <Button
              size="sm"
              variant="outline"
              disabled={(priceList.rules ?? []).length === 0}
              onClick={() => setCreatingMatrix(true)}
            >
              <Plus className="size-4" aria-hidden />
              {t('pricing.matrices.create')}
            </Button>
          </PermissionGuard>
        }
      >
        <DataTable
          columns={matrixColumns}
          rows={priceList.matrices ?? []}
          rowKey={(row) => row.id}
          emptyMessage={t('pricing.matrices.empty')}
        />
      </SectionCard>

      {creatingRule || editingRule ? (
        <PriceRuleDialog
          priceListId={priceList.id}
          rule={editingRule}
          open
          onOpenChange={(open) => {
            if (open) return
            setCreatingRule(false)
            setEditingRule(null)
          }}
        />
      ) : null}

      {creatingMatrix || editingMatrix ? (
        <PriceMatrixDialog
          priceListId={priceList.id}
          matrix={editingMatrix}
          rules={priceList.rules ?? []}
          open
          onOpenChange={(open) => {
            if (open) return
            setCreatingMatrix(false)
            setEditingMatrix(null)
          }}
        />
      ) : null}

      <ConfirmDialog
        open={ruleToDelete !== null}
        onOpenChange={(open) => !open && setRuleToDelete(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: ruleToDelete?.code ?? '' })}
        confirmLabel={t('common.delete')}
        isPending={removeRule.isPending}
        onConfirm={() => {
          if (ruleToDelete === null) return
          removeRule.mutate(ruleToDelete.id, { onSuccess: () => setRuleToDelete(null) })
        }}
      />

      <ConfirmDialog
        open={matrixToDelete !== null}
        onOpenChange={(open) => !open && setMatrixToDelete(null)}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: matrixToDelete?.code ?? '' })}
        confirmLabel={t('common.delete')}
        isPending={removeMatrix.isPending}
        onConfirm={() => {
          if (matrixToDelete === null) return
          removeMatrix.mutate(matrixToDelete.id, { onSuccess: () => setMatrixToDelete(null) })
        }}
      />
    </div>
  )
}

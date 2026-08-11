import { Ban, CircleCheck, Pencil, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { useChangeCustomerStatus, useDeleteCustomer } from '../hooks/useCustomers'
import type { Customer } from '../types/customer'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Button } from '@/shared/components/ui/button'

/**
 * En-tête de la fiche client : identité et actions.
 *
 * Le blocage a **sa propre permission** côté backend, `customers.block`,
 * distincte d'`update` : interrompre les commandes d'un client n'est pas une
 * correction de fiche. Le bouton la respecte, sans quoi l'interface proposerait
 * une action que l'API refuserait.
 */
export function CustomerHeader({ customer }: { customer: Customer }) {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [confirmBlock, setConfirmBlock] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState(false)

  const changeStatus = useChangeCustomerStatus(customer.id)
  const remove = useDeleteCustomer()

  const blocked = customer.status === 'blocked'

  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="text-2xl font-semibold tracking-tight">{customer.name}</h1>
          <StatusBadge status={customer.status} />
        </div>
        <p className="mt-1 text-sm text-muted-foreground">
          {customer.code}
          {customer.legalName ? ` · ${customer.legalName}` : ''}
        </p>
      </div>

      <div className="flex shrink-0 flex-wrap gap-2">
        <PermissionGuard permission="customers.block">
          <Button variant="outline" onClick={() => setConfirmBlock(true)}>
            {blocked ? (
              <CircleCheck className="size-4" aria-hidden />
            ) : (
              <Ban className="size-4" aria-hidden />
            )}
            {blocked ? t('customers.unblock') : t('customers.block')}
          </Button>
        </PermissionGuard>

        <PermissionGuard permission="customers.update">
          <Button variant="outline" asChild>
            <Link to={`/customers/${customer.id}/edit`}>
              <Pencil className="size-4" aria-hidden />
              {t('common.edit')}
            </Link>
          </Button>
        </PermissionGuard>

        <PermissionGuard permission="customers.delete">
          <Button variant="outline" onClick={() => setConfirmDelete(true)}>
            <Trash2 className="size-4" aria-hidden />
            {t('common.delete')}
          </Button>
        </PermissionGuard>
      </div>

      <ConfirmDialog
        open={confirmBlock}
        onOpenChange={setConfirmBlock}
        variant={blocked ? 'default' : 'destructive'}
        title={blocked ? t('customers.unblock') : t('customers.block')}
        description={t(blocked ? 'customers.unblockConfirm' : 'customers.blockConfirm', {
          name: customer.name,
        })}
        confirmLabel={blocked ? t('customers.unblock') : t('customers.block')}
        isPending={changeStatus.isPending}
        onConfirm={() => {
          changeStatus.mutate(blocked ? 'active' : 'blocked', {
            onSuccess: () => setConfirmBlock(false),
          })
        }}
      />

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('confirm.deleteTitle')}
        description={t('customers.deleteConfirm', { name: customer.name })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          remove.mutate(customer.id, {
            onSuccess: () => {
              setConfirmDelete(false)
              void navigate('/customers')
            },
          })
        }}
      />
    </div>
  )
}

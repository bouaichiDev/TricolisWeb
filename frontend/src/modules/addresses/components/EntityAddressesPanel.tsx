import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { AddressCard } from './AddressCard'
import { AddressFormDialog, type EntityAddressFormValues } from './AddressFormDialog'
import { useEntityAddresses } from '../hooks/useEntityAddresses'
import {
  useCreateEntityAddress,
  useDeleteEntityAddress,
  useUpdateEntityAddress,
} from '../hooks/useEntityAddressMutations'
import { toAddressFormValues, toAddressPayload } from '../schemas/addressSchema'
import type { Address, AddressEntityType, EntityAddressLink } from '../types/address'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { Button } from '@/shared/components/ui/button'

interface EntityAddressesPanelProps {
  entityType: AddressEntityType
  entityId: string
  emptyMessage?: string
  hideContacts?: boolean
  /** Lecture seule : ni ajout, ni modification, ni suppression. */
  readOnly?: boolean
}

/** Une carte par liaison : livraison et facturation sont deux entrées distinctes. */
interface Row {
  address: Address
  link: EntityAddressLink | null
}

/**
 * Adresses d'une entité, chacune avec ses contacts.
 *
 * C'est la structure réelle du domaine : un client porte plusieurs adresses —
 * livraison, facturation — et chaque adresse porte les contacts qui la
 * concernent.
 */
export function EntityAddressesPanel({
  entityType,
  entityId,
  emptyMessage,
  hideContacts = false,
  readOnly = false,
}: EntityAddressesPanelProps) {
  const { t } = useTranslation()
  const entity = { entityType, entityId }

  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<Row | null>(null)
  const [deleting, setDeleting] = useState<Address | null>(null)

  const { data, isPending, error, refetch } = useEntityAddresses(entityType, entityId)
  const create = useCreateEntityAddress(entity)
  const update = useUpdateEntityAddress(entity, editing?.address.id ?? '')
  const remove = useDeleteEntityAddress(entity)

  if (isPending) return <ListSkeleton rows={3} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />

  // Une adresse peut porter plusieurs liaisons vers la même entité, avec des
  // types différents. Chacune donne sa propre ligne, sinon l'un des deux rôles
  // disparaîtrait de l'écran.
  const rows: Row[] = (data?.data ?? []).flatMap((address): Row[] => {
    const links = address.links?.filter((link) => link.entityId === entityId) ?? []

    return links.length === 0
      ? [{ address, link: null }]
      : links.map((link) => ({ address, link }))
  })

  const addButton = readOnly ? null : (
    <PermissionGuard permission="addresses.create">
      <Button size="sm" onClick={() => setCreating(true)}>
        <Plus className="size-4" aria-hidden />
        {t('addresses.add')}
      </Button>
    </PermissionGuard>
  )

  return (
    <div className="flex flex-col gap-4">
      {addButton ? <div className="flex justify-end">{addButton}</div> : null}

      {rows.length === 0 ? (
        <EmptyState
          title={emptyMessage ?? t('addresses.empty')}
          description={t('addresses.emptyHint')}
        />
      ) : (
        rows.map((row) => (
          <AddressCard
            key={row.link?.id ?? row.address.id}
            address={row.address}
            addressType={row.link?.addressType}
            isDefault={row.link?.isDefault ?? false}
            hideContacts={hideContacts}
            entity={readOnly ? undefined : entity}
            onEdit={readOnly || row.link === null ? undefined : () => setEditing(row)}
            onDelete={readOnly ? undefined : () => setDeleting(row.address)}
          />
        ))
      )}

      <AddressFormDialog
        open={creating}
        onOpenChange={setCreating}
        title={t('addresses.add')}
        onSubmit={(values: EntityAddressFormValues) =>
          create.mutateAsync({
            ...toAddressPayload(values),
            addressType: values.addressType,
            isDefault: values.isDefault,
          })
        }
      />

      {editing?.link ? (
        <AddressFormDialog
          key={editing.link.id}
          open
          onOpenChange={(open) => !open && setEditing(null)}
          title={t('addresses.edit')}
          defaultValues={{
            ...toAddressFormValues(editing.address),
            addressType: editing.link.addressType ?? 'delivery',
            isDefault: editing.link.isDefault,
          }}
          onSubmit={(values) =>
            update.mutateAsync({
              ...toAddressPayload(values),
              addressType: values.addressType,
              isDefault: values.isDefault,
              currentLinkId: editing.link?.id ?? '',
              currentAddressType: editing.link?.addressType ?? null,
            })
          }
        />
      ) : null}

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('confirm.deleteTitle')}
        description={t('addresses.deleteConfirm')}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          if (deleting === null) return
          remove.mutate(deleting.id, { onSuccess: () => setDeleting(null) })
        }}
      />
    </div>
  )
}

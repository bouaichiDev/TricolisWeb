import { MapPin, Pencil, Trash2, UserPlus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { AddressContactDialog } from './AddressContactDialog'
import { AddressContactList } from './AddressContactList'
import { useAddContactToAddress } from '../hooks/useEntityAddressMutations'
import type { Address, AddressEntityType } from '../types/address'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

/** Lignes affichables d'une adresse, dans l'ordre postal. */
function postalLines(address: Address): string[] {
  return [
    [address.addressNumber, address.route].filter(Boolean).join(' ') || address.addressLine1,
    address.addressLine2,
    [address.postalCode, address.city].filter(Boolean).join(' '),
    address.country,
  ].filter((line): line is string => Boolean(line))
}

interface AddressCardProps {
  address: Address
  /** Type de la liaison pour l'entité consultée : livraison, facturation. */
  addressType?: string | null
  isDefault?: boolean
  /** Masque les contacts, sur les écrans où ils n'ont pas leur place. */
  hideContacts?: boolean
  /** Entité consultée. Absente sur les écrans en lecture seule. */
  entity?: { entityType: AddressEntityType; entityId: string }
  onEdit?: () => void
  onDelete?: () => void
}

/**
 * Adresse en lecture, avec ses contacts.
 *
 * Le type affiché vient de la **liaison** consultée, pas de l'adresse : la même
 * adresse peut être livraison pour un client et facturation pour un autre.
 */
export function AddressCard({
  address,
  addressType,
  isDefault = false,
  hideContacts = false,
  entity,
  onEdit,
  onDelete,
}: AddressCardProps) {
  const { t } = useTranslation()
  const [addingContact, setAddingContact] = useState(false)

  const addContact = useAddContactToAddress(
    entity ?? { entityType: 'customer', entityId: '' },
    address.id,
  )

  const window =
    address.timeWindowFrom && address.timeWindowTo
      ? `${address.timeWindowFrom.slice(0, 5)} – ${address.timeWindowTo.slice(0, 5)}`
      : null

  return (
    <div className="flex flex-col gap-4 rounded-lg border p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="flex flex-wrap items-center gap-2 font-medium">
            <MapPin className="size-4 shrink-0 text-muted-foreground" aria-hidden />
            {address.name ?? address.addressLine1}
            {addressType ? (
              <Badge variant="secondary" className="font-normal">
                {t(`addressTypes.${addressType}`, { defaultValue: addressType })}
              </Badge>
            ) : null}
            {isDefault ? (
              <Badge variant="outline" className="font-normal">
                {t('addresses.default')}
              </Badge>
            ) : null}
          </p>

          <p className="mt-1 whitespace-pre-line text-sm text-muted-foreground">
            {postalLines(address).join('\n')}
          </p>
        </div>

        <div className="flex shrink-0 items-center gap-2">
          {window ? (
            <span className="text-xs text-muted-foreground">
              {t('addresses.fields.timeWindow')} : {window}
            </span>
          ) : null}

          {onEdit ? (
            <PermissionGuard permission="addresses.update">
              <Button variant="ghost" size="icon" aria-label={t('common.edit')} onClick={onEdit}>
                <Pencil className="size-4" aria-hidden />
              </Button>
            </PermissionGuard>
          ) : null}

          {onDelete ? (
            <PermissionGuard permission="addresses.delete">
              <Button
                variant="ghost"
                size="icon"
                aria-label={t('common.delete')}
                onClick={onDelete}
              >
                <Trash2 className="size-4" aria-hidden />
              </Button>
            </PermissionGuard>
          ) : null}
        </div>
      </div>

      {address.instructions ? (
        <p className="text-sm text-muted-foreground">{address.instructions}</p>
      ) : null}

      {hideContacts ? null : (
        <div className="flex flex-col gap-2">
          <div className="flex items-center justify-between gap-4">
            <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('contacts.title')}
            </h4>

            {entity ? (
              <PermissionGuard permission="contacts.create">
                <Button variant="outline" size="sm" onClick={() => setAddingContact(true)}>
                  <UserPlus className="size-4" aria-hidden />
                  {t('addresses.addContact')}
                </Button>
              </PermissionGuard>
            ) : null}
          </div>

          <AddressContactList addressId={address.id} />
        </div>
      )}

      {entity ? (
        <AddressContactDialog
          open={addingContact}
          onOpenChange={setAddingContact}
          onSubmit={(payload) => addContact.mutateAsync(payload)}
        />
      ) : null}
    </div>
  )
}

import { useTranslation } from 'react-i18next'

import { AddressCard } from './AddressCard'
import { useEntityAddresses } from '../hooks/useEntityAddresses'
import type { AddressEntityType } from '../types/address'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'

interface EntityAddressesPanelProps {
  entityType: AddressEntityType
  entityId: string
  emptyMessage?: string
  hideContacts?: boolean
}

/**
 * Adresses d'une entité, chacune avec ses contacts.
 *
 * C'est la structure réelle du domaine : un client porte plusieurs adresses —
 * livraison, facturation — et chaque adresse porte les contacts qui la
 * concernent. La fiche client présentait auparavant une liste de contacts
 * détachée de tout lieu, ce que le modèle ne prévoit pas.
 */
export function EntityAddressesPanel({
  entityType,
  entityId,
  emptyMessage,
  hideContacts = false,
}: EntityAddressesPanelProps) {
  const { t } = useTranslation()
  const { data, isPending, error, refetch } = useEntityAddresses(entityType, entityId)

  if (isPending) return <ListSkeleton rows={3} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />

  const addresses = data?.data ?? []

  if (addresses.length === 0) {
    return (
      <EmptyState
        title={emptyMessage ?? t('addresses.empty')}
        description={t('addresses.emptyHint')}
      />
    )
  }

  return (
    <div className="flex flex-col gap-4">
      {addresses.map((address) => {
        // Une adresse peut porter plusieurs liaisons vers la même entité, avec
        // des types différents — livraison **et** facturation. Chacune donne
        // lieu à sa propre carte, sinon l'un des deux rôles disparaîtrait.
        const links = address.links?.filter((link) => link.entityId === entityId) ?? []

        if (links.length === 0) {
          return <AddressCard key={address.id} address={address} hideContacts={hideContacts} />
        }

        return links.map((link) => (
          <AddressCard
            key={link.id}
            address={address}
            addressType={link.addressType}
            isDefault={link.isDefault}
            hideContacts={hideContacts}
          />
        ))
      })}
    </div>
  )
}

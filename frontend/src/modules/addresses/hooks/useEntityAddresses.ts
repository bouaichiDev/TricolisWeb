import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { addressKeys } from './useAddresses'
import { addressesApi } from '../api/addresses.api'
import type { AddressEntityType } from '../types/address'

export const entityAddressKeys = {
  list: (entityType: AddressEntityType, entityId: string) =>
    [...addressKeys.all, 'entity', entityType, entityId] as const,
  contacts: (addressId: string) => [...addressKeys.all, 'contacts', addressId] as const,
}

/**
 * Adresses d'une entité — client, site, agence.
 *
 * Un client en porte plusieurs : livraison, facturation. Le type est porté par
 * la liaison, renvoyée dans `links` lorsque la liste est filtrée par entité.
 */
export function useEntityAddresses(entityType: AddressEntityType, entityId: string) {
  return useQuery({
    queryKey: entityAddressKeys.list(entityType, entityId),
    queryFn: () => addressesApi.listForEntity(entityType, entityId),
    enabled: entityId !== '',
  })
}

/**
 * Contacts d'une adresse.
 *
 * Les contacts sont rattachés à l'adresse, pas à l'entité : le destinataire
 * d'une livraison dépend du lieu, pas du client dans l'absolu.
 */
export function useAddressContacts(addressId: string) {
  return useQuery({
    queryKey: entityAddressKeys.contacts(addressId),
    queryFn: () => addressesApi.contacts(addressId),
    enabled: addressId !== '',
  })
}

export function useAttachAddressContact(addressId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: { contactId: string; contactRole?: string; isPrimary?: boolean }) =>
      addressesApi.attachContact(addressId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: entityAddressKeys.contacts(addressId) })
      toast.success(t('addresses.contactAttached'))
    },
  })
}

export function useDetachAddressContact(addressId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (linkId: string) => addressesApi.detachContact(addressId, linkId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: entityAddressKeys.contacts(addressId) })
      toast.success(t('addresses.contactDetached'))
    },
  })
}

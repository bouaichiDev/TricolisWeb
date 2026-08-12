import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { entityAddressKeys } from './useEntityAddresses'
import { addressesApi, type AddressPayload } from '../api/addresses.api'
import { contactsApi } from '@/modules/contacts/api/contacts.api'
import type { AddressEntityType } from '../types/address'

interface EntityRef {
  entityType: AddressEntityType
  entityId: string
}

export interface AddressWithTypePayload extends AddressPayload {
  addressType: string
  isDefault: boolean
}

/**
 * Création d'une adresse rattachée à une entité.
 *
 * `POST /addresses` crée l'adresse **et** sa première liaison en un appel :
 * `entityType`, `entityId` et `addressType` y sont acceptés. Une adresse sans
 * liaison serait invisible dans son organisation — le backend ne laisse pas ce
 * cas se produire, et le frontend n'a donc pas à l'enchaîner lui-même.
 */
export function useCreateEntityAddress({ entityType, entityId }: EntityRef) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ addressType, isDefault, ...address }: AddressWithTypePayload) =>
      addressesApi.create({ ...address, entityType, entityId, addressType, isDefault }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: entityAddressKeys.list(entityType, entityId) })
      toast.success(t('toast.created'))
    },
  })
}

/**
 * Modification d'une adresse.
 *
 * Le type est porté par la liaison, que `PATCH /addresses/{id}` ne touche pas.
 * Le changer demande donc de créer la nouvelle liaison **avant** de retirer
 * l'ancienne : l'API refuse de supprimer la dernière liaison d'une adresse, et
 * l'ordre inverse échouerait.
 */
export function useUpdateEntityAddress({ entityType, entityId }: EntityRef, addressId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: async ({
      addressType,
      isDefault,
      currentLinkId,
      currentAddressType,
      ...address
    }: AddressWithTypePayload & { currentLinkId: string; currentAddressType: string | null }) => {
      const updated = await addressesApi.update(addressId, address)

      if (addressType !== currentAddressType) {
        await addressesApi.linkEntity(addressId, { entityType, entityId, addressType, isDefault })
        await addressesApi.unlinkEntity(addressId, currentLinkId)
      }

      return updated
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: entityAddressKeys.list(entityType, entityId) })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteEntityAddress({ entityType, entityId }: EntityRef) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (addressId: string) => addressesApi.remove(addressId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: entityAddressKeys.list(entityType, entityId) })
      toast.success(t('toast.deleted'))
    },
  })
}

export interface ContactEdit extends NewContactPayload {
  contactId: string
  /** Liaison actuelle vers l'adresse, à refaire si le rôle change. */
  linkId: string
  currentRole: string | null
  currentIsPrimary: boolean
}

/**
 * Modification d'un contact rattaché à une adresse.
 *
 * Deux ressources distinctes : le **contact** porte le nom, le téléphone et
 * l'email ; la **liaison** porte le rôle et le drapeau principal. Le premier se
 * modifie par `PATCH /contacts/{id}`. La seconde n'a pas de `PATCH` — seuls
 * `POST` et `DELETE` existent sur `/addresses/{id}/contacts` — elle est donc
 * refaite : la nouvelle liaison est créée avant que l'ancienne soit retirée,
 * pour qu'un échec ne laisse pas le contact détaché.
 */
export function useUpdateAddressContact(addressId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: async (payload: ContactEdit) => {
      await contactsApi.update(payload.contactId, {
        firstName: payload.firstName,
        lastName: payload.lastName,
        phone: payload.phone,
        email: payload.email,
      })

      const linkChanged =
        payload.contactRole !== payload.currentRole || payload.isPrimary !== payload.currentIsPrimary

      if (linkChanged) {
        await addressesApi.attachContact(addressId, {
          contactId: payload.contactId,
          contactRole: payload.contactRole,
          isPrimary: payload.isPrimary,
        })
        await addressesApi.detachContact(addressId, payload.linkId)
      }
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: entityAddressKeys.contacts(addressId) })
      toast.success(t('toast.updated'))
    },
  })
}

export interface NewContactPayload {
  firstName: string
  lastName: string
  phone: string | null
  email: string | null
  contactRole: string
  isPrimary: boolean
}

/**
 * Création d'un contact, puis rattachement à l'adresse.
 *
 * Deux appels : `POST /contacts` crée le contact et le rattache à l'entité,
 * `POST /addresses/{id}/contacts` le rattache au lieu. L'API n'expose pas de
 * création directe sur une adresse, et le contact doit exister dans
 * l'organisation avant d'y être rattaché.
 */
export function useAddContactToAddress({ entityType, entityId }: EntityRef, addressId: string) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: async (payload: NewContactPayload) => {
      const contact = await contactsApi.create({
        firstName: payload.firstName,
        lastName: payload.lastName,
        phone: payload.phone,
        email: payload.email,
        entityType,
        entityId,
        contactRole: payload.contactRole,
        isPrimary: payload.isPrimary,
      })

      return addressesApi.attachContact(addressId, {
        contactId: contact.id,
        contactRole: payload.contactRole,
        isPrimary: payload.isPrimary,
      })
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: entityAddressKeys.contacts(addressId) })
      toast.success(t('addresses.contactAttached'))
    },
  })
}

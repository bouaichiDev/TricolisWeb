import { useCreateCustomerSite, useUpdateCustomerSite } from './useCustomerSites'
import { addressesApi } from '@/modules/addresses/api/addresses.api'
import { useCreateAddress, useUpdateAddress } from '@/modules/addresses/hooks/useAddresses'
import { toAddressPayload } from '@/modules/addresses/schemas/addressSchema'
import type { CustomerSiteFormValues } from '../schemas/customerSiteSchema'

/**
 * Création d'un site : adresse d'abord, site ensuite.
 *
 * L'API impose un `addressId` existant à la création du site ; l'adresse est
 * donc créée en premier, rattachée au client via `entityType` / `entityId`.
 * Les deux appels ne forment pas une transaction : si le second échoue,
 * l'adresse reste, orpheline de site mais liée au client — c'est visible et
 * corrigeable, contrairement à un site sans adresse qui serait invalide.
 */
export function useCreateSiteWithAddress(customerId: string) {
  const createAddress = useCreateAddress()
  const createSite = useCreateCustomerSite(customerId)

  return {
    isPending: createAddress.isPending || createSite.isPending,
    submit: async (values: CustomerSiteFormValues) => {
      /**
       * L'adresse est créée **sans entité**, donc rattachée à l'organisation.
       *
       * Elle ne peut pas viser le site, qui n'existe pas encore — il lui faut
       * un `addressId`. Et elle ne doit pas viser le client : elle apparaîtrait
       * alors dans ses adresses, à côté de ses adresses de livraison et de
       * facturation, alors qu'elle appartient au site. C'était le cas avant
       * cette correction.
       *
       * Le rattachement à l'organisation est la valeur par défaut de l'API
       * (`EntityLinkResolver`) : toute adresse appartient à son organisation,
       * et s'ajoute ensuite aux entités qui l'utilisent.
       */
      const address = await createAddress.mutateAsync(toAddressPayload(values))

      const site = await createSite.mutateAsync({
        addressId: address.id,
        code: values.code,
        name: values.siteName,
        siteType: values.siteType.trim() === '' ? null : values.siteType.trim(),
        isDefault: values.isDefault,
        status: values.status,
      })

      // Le site existe : l'adresse peut enfin le désigner.
      await addressesApi.linkEntity(address.id, {
        entityType: 'customer_site',
        entityId: site.id,
        addressType: 'delivery',
        isDefault: values.isDefault,
      })

      return site
    },
  }
}

/** Mise à jour : chaque ressource est modifiée par sa propre route. */
export function useUpdateSiteWithAddress(customerId: string, siteId: string, addressId: string) {
  const updateAddress = useUpdateAddress(addressId)
  const updateSite = useUpdateCustomerSite(customerId, siteId)

  return {
    isPending: updateAddress.isPending || updateSite.isPending,
    submit: async (values: CustomerSiteFormValues) => {
      await updateAddress.mutateAsync(toAddressPayload(values))

      return updateSite.mutateAsync({
        name: values.siteName,
        siteType: values.siteType.trim() === '' ? null : values.siteType.trim(),
        isDefault: values.isDefault,
        status: values.status,
      })
    },
  }
}

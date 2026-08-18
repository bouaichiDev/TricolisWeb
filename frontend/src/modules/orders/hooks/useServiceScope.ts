import { useMemo } from 'react'

import { useEntityAddresses, useAddressContacts } from '@/modules/addresses/hooks/useEntityAddresses'
import type { Address } from '@/modules/addresses/types/address'
import { useCustomerSiteList } from '@/modules/customerSites/hooks/useCustomerSites'
import { useActiveServices } from '@/modules/services/hooks/useServices'
import type { Service } from '@/modules/services/types/service'
import type { AsyncOption } from '@/shared/components/form/AsyncSelect'

/**
 * Référentiels d'un service de commande : service, adresse, contacts.
 *
 * Les adresses sont chargées **par entité** — client ou site — parce que c'est
 * la seule façon dont l'API les expose (`GET /addresses?entityType=&entityId=`).
 * Charger celles de tous les sites d'un coup demanderait une requête par site :
 * l'écran fait donc choisir la source, puis charge une seule liste.
 */
export function useServiceOptions() {
  const query = useActiveServices()
  const services = useMemo(() => query.data?.data ?? [], [query.data])

  return {
    isLoading: query.isPending,
    services,
    options: services.map(
      (service): AsyncOption => ({
        value: service.id,
        label: service.name,
        hint: service.code,
      }),
    ),
    byId: useMemo(
      () => new Map(services.map((service): [string, Service] => [service.id, service])),
      [services],
    ),
  }
}

/** Sites du client, proposés comme source d'adresses en plus du client lui-même. */
export function useSiteOptions(customerId: string) {
  const query = useCustomerSiteList(customerId)

  return {
    isLoading: customerId !== '' && query.isPending,
    options: (query.data?.data ?? []).map(
      (site): AsyncOption => ({ value: site.id, label: site.name, hint: site.code }),
    ),
  }
}

/** Libellé d'une adresse : le nom s'il existe, la voie sinon, puis la ville. */
export function addressLabel(address: Address): string {
  return address.name ?? address.addressLine1
}

export function addressHint(address: Address): string | undefined {
  const parts = [address.postalCode, address.city].filter((part) => part !== null && part !== '')

  return parts.length > 0 ? parts.join(' ') : undefined
}

export function useAddressOptions(entityType: 'customer' | 'customer_site', entityId: string) {
  const query = useEntityAddresses(entityType, entityId)
  const addresses = query.data?.data ?? []

  return {
    isLoading: entityId !== '' && query.isPending,
    addresses,
    options: addresses.map(
      (address): AsyncOption => ({
        value: address.id,
        label: addressLabel(address),
        hint: addressHint(address),
      }),
    ),
  }
}

/**
 * Contacts rattachés à l'adresse choisie.
 *
 * Le contact d'une intervention dépend du lieu, pas du client dans l'absolu :
 * c'est la raison pour laquelle l'API les porte sur l'adresse.
 */
export function useAddressContactOptions(addressId: string) {
  const query = useAddressContacts(addressId)
  const links = query.data ?? []

  return {
    isLoading: addressId !== '' && query.isPending,
    links,
    options: links.map(
      (link): AsyncOption => ({
        value: link.contact.id,
        label: `${link.contact.firstName} ${link.contact.lastName}`.trim(),
        hint: link.contact.mobile ?? link.contact.phone ?? link.contact.email ?? undefined,
      }),
    ),
  }
}

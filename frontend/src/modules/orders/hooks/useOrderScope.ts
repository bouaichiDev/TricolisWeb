import { useQuery } from '@tanstack/react-query'

import { agenciesApi } from '@/modules/agencies/api/agencies.api'
import { customersApi } from '@/modules/customers/api/customers.api'
import { depotsApi } from '@/modules/depots/api/depots.api'
import type { AsyncOption } from '@/shared/components/form/AsyncSelect'

/**
 * Sélections dépendantes de l'en-tête d'une commande.
 *
 * Le §49 du premier prompt l'exige : le frontend ne doit pas envoyer un dépôt
 * incompatible avec l'agence choisie. Les dépôts n'existent d'ailleurs que sous
 * une agence côté API — `/agencies/{agency}/depots` — donc la dépendance n'est
 * pas seulement une précaution d'interface, c'est la forme de la route.
 *
 * L'organisation active est portée par l'en-tête `X-Organization-Id` du client
 * HTTP : aucune de ces listes ne peut déborder sur une autre organisation.
 */
export function useCustomerOptions(search: string) {
  const query = useQuery({
    queryKey: ['orders', 'scope', 'customers', search],
    queryFn: () =>
      customersApi.list({ page: 1, perPage: 50, search: search || undefined, status: 'active' }),
    staleTime: 60 * 1000,
  })

  return {
    isLoading: query.isPending,
    options: (query.data?.data ?? []).map(
      (customer): AsyncOption => ({
        value: customer.id,
        label: customer.name,
        hint: customer.code,
      }),
    ),
  }
}

export function useAgencyOptions() {
  const query = useQuery({
    queryKey: ['orders', 'scope', 'agencies'],
    queryFn: () => agenciesApi.list({ page: 1, perPage: 100, status: 'active' }),
    staleTime: 10 * 60 * 1000,
  })

  return {
    isLoading: query.isPending,
    options: (query.data?.data ?? []).map(
      (agency): AsyncOption => ({ value: agency.id, label: agency.name, hint: agency.code }),
    ),
  }
}

/** Dépôts de l'agence choisie. Sans agence, la requête n'est pas lancée. */
export function useDepotOptions(agencyId: string) {
  const query = useQuery({
    queryKey: ['orders', 'scope', 'depots', agencyId],
    queryFn: () => depotsApi.list(agencyId, { perPage: 100 }),
    enabled: agencyId !== '',
    staleTime: 10 * 60 * 1000,
  })

  return {
    isLoading: agencyId !== '' && query.isPending,
    options: (query.data?.data ?? []).map(
      (depot): AsyncOption => ({ value: depot.id, label: depot.name, hint: depot.code }),
    ),
  }
}

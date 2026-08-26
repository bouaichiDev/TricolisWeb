import { useQuery } from '@tanstack/react-query'

import { contactsApi } from '../api/contacts.api'

export const contactKeys = {
  all: ['contacts'] as const,
  list: (search?: string) => [...contactKeys.all, 'list', search ?? ''] as const,
}

/**
 * Contacts de l'organisation, prêts pour une liste déroulante.
 *
 * `GET /contacts` ne rend que ceux rattachés à l'organisation active — la
 * table n'a pas d'`organization_id`, elle le tient de ses liaisons. Le serveur
 * revérifie cette appartenance à l'écriture.
 */
export function useContactOptions(search?: string) {
  const query = useQuery({
    queryKey: contactKeys.list(search),
    queryFn: () => contactsApi.list({ page: 1, perPage: 100, search }),
    staleTime: 5 * 60 * 1000,
  })

  return {
    isLoading: query.isPending,
    options: (query.data?.data ?? []).map((contact) => ({
      value: contact.id,
      label: `${contact.firstName ?? ''} ${contact.lastName ?? ''}`.trim() || contact.email || contact.id,
      hint: contact.email ?? undefined,
    })),
  }
}

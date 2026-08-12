import { useQueries } from '@tanstack/react-query'

import { agenciesApi } from '@/modules/agencies/api/agencies.api'
import { customersApi } from '@/modules/customers/api/customers.api'
import { membersApi } from '@/modules/users/api/members.api'
import { rolesApi } from '@/modules/roles/api/roles.api'

export interface Counter {
  key: string
  to: string
  permission: string
  total: number | null
  isPending: boolean
}

/**
 * Compteurs du tableau de bord.
 *
 * Le backend n'expose **aucun endpoint d'agrégation** : le seul chiffre
 * disponible est `meta.total`, renvoyé par chaque liste paginée. On demande donc
 * une page d'un seul élément par module — c'est la requête la plus légère qui
 * donne un total exact, et elle ne transporte aucune donnée inutile.
 */
export function useCounters(): Counter[] {
  const results = useQueries({
    queries: [
      {
        queryKey: ['dashboard', 'customers'],
        queryFn: () => customersApi.list({ page: 1, perPage: 1 }),
      },
      {
        queryKey: ['dashboard', 'agencies'],
        queryFn: () => agenciesApi.list({ page: 1, perPage: 1 }),
      },
      {
        queryKey: ['dashboard', 'users'],
        queryFn: () => membersApi.list({ page: 1, perPage: 1 }),
      },
      {
        queryKey: ['dashboard', 'roles'],
        queryFn: () => rolesApi.list({ page: 1, perPage: 1 }),
      },
    ],
  })

  const definitions = [
    { key: 'customers', to: '/customers', permission: 'customers.view' },
    { key: 'agencies', to: '/agencies', permission: 'agencies.view' },
    { key: 'users', to: '/users', permission: 'users.view' },
    { key: 'roles', to: '/roles', permission: 'roles.view' },
  ]

  return definitions.map((definition, index) => ({
    ...definition,
    total: results[index]?.data?.meta?.total ?? null,
    isPending: results[index]?.isPending ?? true,
  }))
}

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { ApiError } from '@/shared/api/errors'

import { statusesApi } from '../api/statuses.api'
import { statusKeys } from './useStatuses'

const defaultsKey = [...statusKeys.all, 'defaults'] as const

/**
 * Statut par défaut de chaque entité.
 *
 * Cache long : le réglage ne bouge qu'à l'initiative d'un administrateur, et
 * chaque formulaire de création le relit pour se préremplir.
 */
export function useStatusDefaults(enabled = true) {
  return useQuery({
    queryKey: defaultsKey,
    queryFn: () => statusesApi.defaults(),
    enabled,
    staleTime: 10 * 60 * 1000,
  })
}

/**
 * Le code qu'une entité reçoit à sa création : le choix de l'administrateur,
 * sinon la valeur de la colonne. `null` tant que le réglage n'a pas répondu —
 * le serveur appliquera alors lui-même le bon statut.
 */
export function useDefaultStatusCode(source: string): string | null {
  const { data } = useStatusDefaults(source !== '')
  const row = data?.find((item) => item.source === source)

  return row?.code ?? row?.systemDefault ?? null
}

/**
 * Préremplit un champ de statut vide avec le statut par défaut de l'entité.
 *
 * Un formulaire de création proposait jusqu'ici une valeur écrite dans le code
 * — « draft » — que l'administrateur ne pouvait pas changer. Le champ reste
 * modifiable : c'est un point de départ, pas une obligation.
 *
 * `allowed` écarte un code que le champ ne sait pas proposer — une énumération,
 * par exemple : mieux vaut un champ vide, que le serveur remplira, qu'une
 * valeur qu'il refuserait.
 */
export function usePrefillDefaultStatus(
  source: string,
  value: string,
  apply: (code: string) => void,
  allowed?: readonly string[],
) {
  const code = useDefaultStatusCode(source)

  useEffect(() => {
    if (value !== '' || code === null) return
    if (allowed !== undefined && !allowed.includes(code)) return

    apply(code)
  }, [value, code, apply, allowed])
}

export function useSetStatusDefault() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ source, statusId }: { source: string; statusId: string | null }) =>
      statusesApi.setDefault(source, statusId),
    onSuccess: (rows) => {
      queryClient.setQueryData(defaultsKey, rows)
      // La colonne « Réglages » de la liste montre la case « par défaut ».
      void queryClient.invalidateQueries({ queryKey: statusKeys.lists() })
      toast.success(t('toast.updated'))
    },
    onError: (error) => {
      toast.error(
        error instanceof ApiError
          ? (Object.values(error.errors)[0]?.[0] ?? error.message)
          : t('errors.unexpected'),
      )
    },
  })
}

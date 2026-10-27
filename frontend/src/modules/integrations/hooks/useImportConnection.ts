import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { importConnectionApi } from '../api/import-connection.api'
import type { ImportConnectionPayload } from '../types/importConnection'

const keys = {
  targetFields: ['integrations', 'import-target-fields'] as const,
  connection: (id: string) => ['integrations', 'import-connection', id] as const,
}

/** La liste des champs cibles ne change qu'avec le code : lue une fois. */
export const useImportTargetFields = () =>
  useQuery({ queryKey: keys.targetFields, queryFn: importConnectionApi.targetFields, staleTime: Infinity })

export const useImportConnection = (id: string) =>
  useQuery({ queryKey: keys.connection(id), queryFn: () => importConnectionApi.get(id) })

export function useSaveImportConnection(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ImportConnectionPayload) => importConnectionApi.save(id, payload),
    onSuccess: (state) => queryClient.setQueryData(keys.connection(id), state),
  })
}

export const useTestImportConnection = (id: string) => useMutation({ mutationFn: () => importConnectionApi.test(id) })

export const useRunImport = (id: string) => useMutation({ mutationFn: () => importConnectionApi.run(id) })

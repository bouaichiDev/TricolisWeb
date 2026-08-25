import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { typeItemsApi, typeSourcesApi } from '../api/types.api'
import type {
  TypeItemFilters,
  TypeItemPayload,
  TypeSourcePayload,
} from '../types/type'

export const typeKeys = {
  all: ['types'] as const,
  sources: (search?: string) => [...typeKeys.all, 'sources', search ?? ''] as const,
  items: (filters: TypeItemFilters) => [...typeKeys.all, 'items', filters] as const,
  /** Options d'une source, servies aux listes déroulantes. */
  options: (code: string) => [...typeKeys.all, 'options', code] as const,
}

export function useTypeSources(search?: string) {
  return useQuery({
    queryKey: typeKeys.sources(search),
    queryFn: () => typeSourcesApi.list(search),
    placeholderData: (previous) => previous,
  })
}

export function useTypeItems(filters: TypeItemFilters, enabled = true) {
  return useQuery({
    queryKey: typeKeys.items(filters),
    queryFn: () => typeItemsApi.list(filters),
    enabled: enabled && (filters.typeId !== undefined || filters.type !== undefined),
    placeholderData: (previous) => previous,
  })
}

/**
 * Valeurs d'une source, prêtes pour une liste déroulante.
 *
 * Le code sert d'indication sous le libellé : deux valeurs peuvent porter un
 * nom voisin, leur code les sépare. Cache long — un référentiel change rarement.
 */
export function useTypeItemOptions(typeCode: string) {
  const query = useQuery({
    queryKey: typeKeys.options(typeCode),
    queryFn: () => typeItemsApi.list({ page: 1, perPage: 100, type: typeCode }),
    staleTime: 10 * 60 * 1000,
  })

  return {
    isLoading: query.isPending,
    options: (query.data?.data ?? []).map((item) => ({
      value: item.id,
      label: item.name,
      hint: item.code,
    })),
  }
}

/** Invalide tout le module : sources, valeurs et options d'un même tenant. */
function useInvalidate() {
  const queryClient = useQueryClient()

  return () => void queryClient.invalidateQueries({ queryKey: typeKeys.all })
}

export function useCreateTypeSource() {
  const invalidate = useInvalidate()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: TypeSourcePayload) => typeSourcesApi.create(payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateTypeSource() {
  const invalidate = useInvalidate()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, ...payload }: Partial<TypeSourcePayload> & { id: string }) =>
      typeSourcesApi.update(id, payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteTypeSource() {
  const invalidate = useInvalidate()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => typeSourcesApi.remove(id),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.deleted'))
    },
  })
}

export function useCreateTypeItem() {
  const invalidate = useInvalidate()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: TypeItemPayload) => typeItemsApi.create(payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateTypeItem() {
  const invalidate = useInvalidate()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, ...payload }: Partial<Omit<TypeItemPayload, 'typeId'>> & { id: string }) =>
      typeItemsApi.update(id, payload),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteTypeItem() {
  const invalidate = useInvalidate()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => typeItemsApi.remove(id),
    onSuccess: () => {
      invalidate()
      toast.success(t('toast.deleted'))
    },
  })
}

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { referentialsApi, type ReferentialPayload } from '../api/referentials.api'
import type { ReferentialFilters, ReferentialKind } from '../types/referential'

/**
 * Une fabrique par référentiel, la sorte servant de préfixe.
 *
 * Les deux référentiels partagent leur code mais pas leur cache : masquer un
 * type de colis ne doit pas invalider les types de regroupement.
 */
export const packageTypeKeys = {
  all: ['package-types'] as const,
  list: (filters: ReferentialFilters) => [...packageTypeKeys.all, 'list', filters] as const,
}

export const groupingTypeKeys = {
  all: ['package-grouping-types'] as const,
  list: (filters: ReferentialFilters) => [...groupingTypeKeys.all, 'list', filters] as const,
}

function keysFor(kind: ReferentialKind) {
  return kind === 'package-types' ? packageTypeKeys : groupingTypeKeys
}

export function useReferentialList(kind: ReferentialKind, filters: ReferentialFilters) {
  return useQuery({
    queryKey: keysFor(kind).list(filters),
    queryFn: () => referentialsApi.list(kind, filters),
    placeholderData: (previous) => previous,
  })
}

/**
 * Référentiel complet pour les listes déroulantes du formulaire de colis.
 *
 * Cache long : ces deux référentiels changent rarement.
 */
export function useReferentialOptions(kind: ReferentialKind) {
  return useQuery({
    queryKey: [...keysFor(kind).all, 'options'],
    queryFn: () => referentialsApi.list(kind, { page: 1, perPage: 100 }),
    staleTime: 10 * 60 * 1000,
  })
}

/**
 * Référentiel prêt pour une liste déroulante.
 *
 * Le code sert d'indication sous le libellé : deux types de colis peuvent
 * porter un nom voisin, leur code les sépare.
 */
export function useReferentialSelectOptions(kind: ReferentialKind) {
  const query = useReferentialOptions(kind)

  return {
    isLoading: query.isPending,
    options: (query.data?.data ?? []).map((item) => ({
      value: item.id,
      label: item.name,
      hint: item.code,
    })),
  }
}

export function useCreateReferential(kind: ReferentialKind) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: ReferentialPayload) => referentialsApi.create(kind, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: keysFor(kind).all })
      toast.success(t('toast.created'))
    },
  })
}

export function useUpdateReferential(kind: ReferentialKind) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: ({ id, ...payload }: ReferentialPayload & { id: string }) =>
      referentialsApi.update(kind, id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: keysFor(kind).all })
      toast.success(t('toast.updated'))
    },
  })
}

export function useDeleteReferential(kind: ReferentialKind) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (id: string) => referentialsApi.remove(kind, id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: keysFor(kind).all })
      toast.success(t('toast.deleted'))
    },
  })
}

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { ApiError } from '@/shared/api/errors'

import { stockMovementsApi, type StockMovementPayload } from '../api/stock-movements.api'
import { stockKeys } from './stockKeys'
import type { StockMovementFilters } from '../types/stockFilters'

export function useStockMovements(filters: StockMovementFilters, enabled = true) {
  return useQuery({
    queryKey: stockKeys.movementList(filters),
    queryFn: () => stockMovementsApi.list(filters),
    enabled,
    placeholderData: (previous) => previous,
  })
}

export function useStockMovement(id: string | undefined) {
  return useQuery({
    queryKey: stockKeys.movement(id ?? ''),
    queryFn: () => stockMovementsApi.get(id ?? ''),
    enabled: id !== undefined && id !== '',
  })
}

/**
 * Enregistre un mouvement.
 *
 * **Aucune quantité ne se saisit directement.** `CreateStockMovementAction`
 * verrouille les soldes, contrôle la disponibilité, écrit le mouvement puis
 * recalcule — dans une transaction. Un solde modifié à la main n'aurait pas
 * d'histoire, et deux corrections concurrentes s'écraseraient.
 *
 * **Aucune mise à jour optimiste.** Le disponible dépend de ce que les autres
 * sessions ont réservé entre-temps ; anticiper le résultat afficherait un
 * chiffre que le serveur va démentir. On attend, puis on relit.
 *
 * Un 409 — stock insuffisant — n'est pas une erreur d'affichage : les soldes
 * sont rechargés, parce que la valeur qui avait décidé de la saisie est
 * périmée, et le message du serveur est montré tel quel.
 */
export function useCreateStockMovement() {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: StockMovementPayload) => stockMovementsApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: stockKeys.balances() })
      void queryClient.invalidateQueries({ queryKey: stockKeys.movements() })
      // La fiche de l'article porte ses soldes : sans cela, elle afficherait
      // encore la quantité d'avant le mouvement.
      void queryClient.invalidateQueries({ queryKey: stockKeys.items() })
      toast.success(t('stock.movementCreated'))
    },
    // Le message du refus n'est pas affiché ici : l'appelant le pose sur son
    // formulaire, là où la saisie fautive est encore visible. Un toast en plus
    // dirait deux fois la même chose. Ce qui se fait ici, en revanche, est ce
    // qu'aucun écran ne peut faire seul : relire les soldes, parce que la
    // valeur qui avait décidé de la saisie vient d'être démentie.
    onError: (error) => {
      if (error instanceof ApiError && error.status === 409) {
        void queryClient.invalidateQueries({ queryKey: stockKeys.balances() })
      }
    },
  })
}

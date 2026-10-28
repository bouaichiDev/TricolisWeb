import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { toast } from 'sonner'

import { refusalMessage } from '@/shared/api/errors'

/**
 * Une transition d'exécution : en cas de succès, la tournée est rechargée
 * (statut, arrêts, actions offertes) ; en cas de refus, la raison donnée par
 * le serveur est gardée pour être affichée à côté du bouton.
 */
export function useTourExecution(tourId: string) {
  const queryClient = useQueryClient()
  const [refusal, setRefusal] = useState<string | null>(null)

  const mutation = useMutation({
    mutationFn: (run: () => Promise<unknown>) => run(),
    onMutate: () => setRefusal(null),
    onSuccess: () => toast.success('Enregistré.'),
    onError: (error) => setRefusal(refusalMessage(error)),
    // Succès ou refus (un autre a pu changer la tournée) : on recharge, et
    // `isPending` reste vrai jusqu'au retour — pas de double clic sur des
    // boutons périmés.
    onSettled: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: ['tour', tourId] }),
        queryClient.invalidateQueries({ queryKey: ['tours'] }),
      ]),
  })

  return { run: mutation.mutate, isPending: mutation.isPending, refusal }
}

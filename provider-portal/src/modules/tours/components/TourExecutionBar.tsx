import { CheckCheck, Play } from 'lucide-react'
import { useState } from 'react'

import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'

import { toursApi } from '../api/toursApi'
import { useTourExecution } from '../hooks/useTourExecution'
import type { Tour } from '../types'

/**
 * Démarrer ou terminer la tournée, selon ce que le serveur ouvre
 * (`actions.canStart`, `actions.canComplete`). Terminer demande confirmation :
 * une tournée terminée ne se rouvre pas depuis le portail.
 */
export function TourExecutionBar({ tour }: { tour: Tour }) {
  const { run, isPending, refusal } = useTourExecution(tour.id)
  const [confirming, setConfirming] = useState(false)

  if (!tour.actions.canStart && !tour.actions.canComplete) return null

  return (
    <div className="flex flex-col gap-2">
      {refusal && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{refusal}</AlertDescription>
        </Alert>
      )}
      <div className="flex flex-wrap gap-2">
        {tour.actions.canStart && (
          <Button disabled={isPending} onClick={() => run(() => toursApi.start(tour.id))}>
            <Play className="size-4" aria-hidden /> Démarrer
          </Button>
        )}
        {tour.actions.canComplete && (
          <Button variant="outline" disabled={isPending} onClick={() => setConfirming(true)}>
            <CheckCheck className="size-4" aria-hidden /> Terminer la tournée
          </Button>
        )}
      </div>
      <ConfirmDialog
        open={confirming}
        onOpenChange={setConfirming}
        title="Terminer la tournée ?"
        description="Tous les arrêts doivent être terminés ou passés. Une tournée terminée ne se rouvre pas depuis le portail."
        confirmLabel="Terminer"
        isPending={isPending}
        onConfirm={() => {
          setConfirming(false)
          run(() => toursApi.complete(tour.id))
        }}
      />
    </div>
  )
}

import { useState } from 'react'

import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'
import { Label } from '@/shared/components/ui/label'
import { Textarea } from '@/shared/components/ui/textarea'

import { toursApi } from '../api/toursApi'
import { useTourExecution } from '../hooks/useTourExecution'
import type { TourStop } from '../types'

/**
 * Faire avancer un arrêt : arrivé, terminé, ou passé avec un motif. Les
 * boutons suivent `stop.actions` ; l'heure est celle du serveur.
 */
export function StopActions({ tourId, stop }: { tourId: string; stop: TourStop }) {
  const { run, isPending, refusal } = useTourExecution(tourId)
  const [skipping, setSkipping] = useState(false)
  const [reason, setReason] = useState('')
  const actions = stop.actions

  if (!actions || (!actions.canArrive && !actions.canComplete && !actions.canSkip)) return null

  return (
    <div className="mt-3 flex flex-col gap-2 border-t pt-3">
      <div className="flex flex-wrap gap-2">
        {actions.canArrive && (
          <Button size="sm" disabled={isPending} onClick={() => run(() => toursApi.stop(tourId, stop.id, 'arrive'))}>
            Arrivé
          </Button>
        )}
        {actions.canComplete && (
          <Button size="sm" disabled={isPending} onClick={() => run(() => toursApi.stop(tourId, stop.id, 'complete'))}>
            Terminé
          </Button>
        )}
        {actions.canSkip && (
          <Button size="sm" variant="outline" disabled={isPending} onClick={() => setSkipping(true)}>
            Passer
          </Button>
        )}
      </div>
      {refusal && <p className="text-destructive text-sm">{refusal}</p>}
      <Dialog open={skipping} onOpenChange={setSkipping}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Passer l’arrêt {stop.sequence}</DialogTitle>
            <DialogDescription>Le motif est transmis à votre transporteur.</DialogDescription>
          </DialogHeader>
          <div className="grid gap-1.5">
            <Label htmlFor={`skip-${stop.id}`}>Motif</Label>
            <Textarea id={`skip-${stop.id}`} value={reason} maxLength={500} onChange={(event) => setReason(event.target.value)} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setSkipping(false)}>
              Annuler
            </Button>
            <Button
              disabled={isPending || reason.trim().length < 3}
              onClick={() => {
                setSkipping(false)
                run(() => toursApi.stop(tourId, stop.id, 'skip', reason.trim()))
              }}
            >
              Passer l’arrêt
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

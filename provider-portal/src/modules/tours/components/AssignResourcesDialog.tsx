import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'

import { driversApi } from '@/modules/drivers/api/driversApi'
import { vehiclesApi } from '@/modules/vehicles/api/vehiclesApi'
import { ApiError, refusalMessage } from '@/shared/api/errors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'
import { Label } from '@/shared/components/ui/label'

import { toursApi } from '../api/toursApi'
import type { Tour } from '../types'

const ACTIVE = { search: '', status: 'active', page: 1, perPage: 100 }
const selectClass = 'border-input h-9 w-full rounded-md border bg-transparent px-3 text-sm'

/**
 * Choisir le chauffeur et le véhicule d'une tournée parmi **sa** flotte active.
 * Le serveur revérifie tout (appartenance, créneau, capacité, statut) et dit
 * pourquoi il refuse.
 */
export function AssignResourcesDialog({ tour, open, onOpenChange }: { tour: Tour; open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const drivers = useQuery({ queryKey: ['drivers', ACTIVE], queryFn: () => driversApi.list(ACTIVE), enabled: open })
  const vehicles = useQuery({ queryKey: ['vehicles', ACTIVE], queryFn: () => vehiclesApi.list(ACTIVE), enabled: open })
  const [driverId, setDriverId] = useState(tour.driver?.id ?? '')
  const [vehicleId, setVehicleId] = useState(tour.vehicle?.id ?? '')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [pending, setPending] = useState(false)

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    setErrors({})
    setPending(true)

    try {
      const updated = await toursApi.assign(tour.id, { driverId: driverId || null, vehicleId: vehicleId || null })
      queryClient.setQueryData(['tour', tour.id], updated)
      void queryClient.invalidateQueries({ queryKey: ['tours'] })
      toast.success('Affectation enregistrée.')
      onOpenChange(false)
    } catch (caught) {
      const fields = caught instanceof ApiError ? caught.errors : {}
      setErrors(Object.keys(fields).length > 0 ? Object.fromEntries(Object.entries(fields).map(([key, messages]) => [key, messages[0]])) : { form: refusalMessage(caught) })
    } finally {
      setPending(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Affecter la tournée {tour.tourNumber}</DialogTitle>
          <DialogDescription>Seuls vos chauffeurs et véhicules actifs sont proposés.</DialogDescription>
        </DialogHeader>
        <form className="space-y-4" noValidate onSubmit={(event) => void submit(event)}>
          {(errors.form ?? errors.tour) && (
            <Alert variant="destructive" role="alert">
              <AlertDescription>{errors.form ?? errors.tour}</AlertDescription>
            </Alert>
          )}
          <div className="grid gap-1.5">
            <Label htmlFor="assign-driver">Chauffeur</Label>
            <select id="assign-driver" className={selectClass} value={driverId} onChange={(event) => setDriverId(event.target.value)}>
              <option value="">Aucun</option>
              {(drivers.data?.data ?? []).map((driver) => (
                <option key={driver.id} value={driver.id}>{`${driver.name} (${driver.code})`}</option>
              ))}
            </select>
            {errors.driverId && <p className="text-destructive text-sm">{errors.driverId}</p>}
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="assign-vehicle">Véhicule</Label>
            <select id="assign-vehicle" className={selectClass} value={vehicleId} onChange={(event) => setVehicleId(event.target.value)}>
              <option value="">Aucun</option>
              {(vehicles.data?.data ?? []).map((vehicle) => (
                <option key={vehicle.id} value={vehicle.id}>{`${vehicle.code} · ${vehicle.registrationNumber}`}</option>
              ))}
            </select>
            {errors.vehicleId && <p className="text-destructive text-sm">{errors.vehicleId}</p>}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={pending}>
              Enregistrer
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

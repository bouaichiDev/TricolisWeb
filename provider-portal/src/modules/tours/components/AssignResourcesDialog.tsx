import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState, type FormEvent } from 'react'
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
 * Seul ce qui change part au serveur, qui revérifie tout (appartenance,
 * créneau, capacité, statut) et dit pourquoi il refuse. L'affectation actuelle
 * reste proposée même si elle n'est plus active.
 */
export function AssignResourcesDialog({ tour, open, onOpenChange }: { tour: Tour; open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const drivers = useQuery({ queryKey: ['drivers', ACTIVE], queryFn: () => driversApi.list(ACTIVE), enabled: open })
  const vehicles = useQuery({ queryKey: ['vehicles', ACTIVE], queryFn: () => vehiclesApi.list(ACTIVE), enabled: open })
  const current = { driverId: tour.driver?.id ?? '', vehicleId: tour.vehicle?.id ?? '' }
  const [choice, setChoice] = useState(current)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [pending, setPending] = useState(false)

  useEffect(() => {
    if (open) {
      setChoice({ driverId: tour.driver?.id ?? '', vehicleId: tour.vehicle?.id ?? '' })
      setErrors({})
    }
  }, [open, tour.driver?.id, tour.vehicle?.id])

  const driverOptions = [...(drivers.data?.data ?? []).map((d) => ({ id: d.id, label: `${d.name} (${d.code})` }))]
  if (tour.driver && !driverOptions.some((option) => option.id === tour.driver?.id)) driverOptions.unshift({ id: tour.driver.id, label: `${tour.driver.name} (${tour.driver.code})` })
  const vehicleOptions = [...(vehicles.data?.data ?? []).map((v) => ({ id: v.id, label: `${v.code} · ${v.registrationNumber}` }))]
  if (tour.vehicle && !vehicleOptions.some((option) => option.id === tour.vehicle?.id)) vehicleOptions.unshift({ id: tour.vehicle.id, label: `${tour.vehicle.code} · ${tour.vehicle.registrationNumber}` })

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    setErrors({})
    const payload = Object.fromEntries(
      (['driverId', 'vehicleId'] as const).filter((key) => choice[key] !== current[key]).map((key) => [key, choice[key] || null]),
    )

    if (Object.keys(payload).length === 0) return onOpenChange(false)

    setPending(true)
    try {
      await toursApi.assign(tour.id, payload)
      // La réponse ne porte pas les arrêts : on recharge le détail plutôt que de l'écraser.
      await queryClient.invalidateQueries({ queryKey: ['tour', tour.id] })
      void queryClient.invalidateQueries({ queryKey: ['tours'] })
      toast.success('Affectation enregistrée.')
      onOpenChange(false)
    } catch (caught) {
      const fields = caught instanceof ApiError ? caught.errors : {}
      const known = Object.fromEntries(Object.entries(fields).map(([key, messages]) => [key, messages[0]]))
      setErrors(known.driverId || known.vehicleId ? known : { form: Object.values(known)[0] ?? refusalMessage(caught) })
    } finally {
      setPending(false)
    }
  }

  const select = (field: 'driverId' | 'vehicleId', label: string, options: { id: string; label: string }[]) => (
    <div className="grid gap-1.5">
      <Label htmlFor={`assign-${field}`}>{label}</Label>
      <select id={`assign-${field}`} className={selectClass} value={choice[field]} onChange={(event) => setChoice({ ...choice, [field]: event.target.value })}>
        <option value="">Aucun</option>
        {options.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}
      </select>
      {errors[field] && <p className="text-destructive text-sm">{errors[field]}</p>}
    </div>
  )

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Affecter la tournée {tour.tourNumber}</DialogTitle>
          <DialogDescription>Seuls vos chauffeurs et véhicules actifs sont proposés.</DialogDescription>
        </DialogHeader>
        <form className="space-y-4" noValidate onSubmit={(event) => void submit(event)}>
          {errors.form && (
            <Alert variant="destructive" role="alert">
              <AlertDescription>{errors.form}</AlertDescription>
            </Alert>
          )}
          {select('driverId', 'Chauffeur', driverOptions)}
          {select('vehicleId', 'Véhicule', vehicleOptions)}
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

import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'

import { errorMessage } from '@/shared/api/errors'
import { Field } from '@/shared/components/form/Field'
import { SelectField } from '@/shared/components/form/SelectField'
import { applyServerErrors } from '@/shared/components/form/serverErrors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'

import { vehiclesApi } from '../api/vehiclesApi'
import { useVehicleTypes } from '../hooks/useVehicles'
import type { Vehicle } from '../types'

const amount = (max: number) => z.string().trim().min(1, 'Valeur requise.').refine((value) => Number(value) >= 0 && Number(value) <= max, 'Valeur hors limites.')

const schema = z.object({
  code: z.string().trim().min(1, 'Code requis.').max(64),
  registrationNumber: z.string().trim().min(1, 'Immatriculation requise.').max(32),
  vehicleTypeId: z.string().min(1, 'Type requis.'),
  payloadCapacity: amount(999_999_999),
  volumeCapacity: amount(99_999_999),
  palletCapacity: amount(10_000).refine((value) => Number.isInteger(Number(value)), 'Nombre entier attendu.'),
})

type Values = z.infer<typeof schema>
const FIELDS = ['code', 'registrationNumber', 'vehicleTypeId', 'payloadCapacity', 'volumeCapacity', 'palletCapacity'] as const

/**
 * Ajout ou modification d'un véhicule. Le type vient du référentiel de
 * l'organisation ; le serveur revérifie tout, et refuse sans dire par qui une
 * immatriculation déjà prise.
 */
export function VehicleDialog({ open, onOpenChange, vehicle }: { open: boolean; onOpenChange: (open: boolean) => void; vehicle?: Vehicle }) {
  const queryClient = useQueryClient()
  const types = useVehicleTypes(open)
  const [error, setError] = useState<string | null>(null)
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    values: {
      code: vehicle?.code ?? '', registrationNumber: vehicle?.registrationNumber ?? '', vehicleTypeId: vehicle?.vehicleType?.id ?? '',
      payloadCapacity: vehicle ? String(Number(vehicle.payloadCapacity)) : '', volumeCapacity: vehicle ? String(Number(vehicle.volumeCapacity)) : '',
      palletCapacity: vehicle ? String(vehicle.palletCapacity) : '',
    },
  })

  const submit = async (values: Values) => {
    setError(null)
    const payload = {
      ...values,
      payloadCapacity: Number(values.payloadCapacity),
      volumeCapacity: Number(values.volumeCapacity),
      palletCapacity: Number(values.palletCapacity),
    }

    try {
      if (vehicle) await vehiclesApi.update(vehicle.id, payload)
      else await vehiclesApi.create(payload)
      await queryClient.invalidateQueries({ queryKey: ['vehicles'] })
      toast.success(vehicle ? 'Véhicule modifié.' : 'Véhicule ajouté.')
      if (!vehicle) form.reset()
      onOpenChange(false)
    } catch (caught) {
      const orphans = applyServerErrors(form, caught, FIELDS)
      setError(orphans[0] ?? (orphans.length === 0 ? errorMessage(caught) : null))
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{vehicle ? 'Modifier le véhicule' : 'Ajouter un véhicule'}</DialogTitle>
          <DialogDescription>Capacités utilisées pour vérifier les affectations aux tournées.</DialogDescription>
        </DialogHeader>
        <form className="space-y-4" noValidate onSubmit={form.handleSubmit(submit)}>
          {error && (
            <Alert variant="destructive" role="alert">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
          <div className="grid gap-4 sm:grid-cols-2">
            <Field form={form} name="code" label="Code" />
            <Field form={form} name="registrationNumber" label="Immatriculation" />
          </div>
          <SelectField form={form} name="vehicleTypeId" label="Type de véhicule" placeholder="Choisir…" options={(types.data ?? []).map((type) => ({ value: type.id, label: type.name }))} />
          <div className="grid gap-4 sm:grid-cols-3">
            <Field form={form} name="payloadCapacity" label="Charge utile (kg)" inputMode="decimal" />
            <Field form={form} name="volumeCapacity" label="Volume (m³)" inputMode="decimal" />
            <Field form={form} name="palletCapacity" label="Palettes" inputMode="numeric" />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              Enregistrer
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

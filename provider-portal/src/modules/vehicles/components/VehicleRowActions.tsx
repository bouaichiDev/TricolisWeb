import { Pencil, PowerOff, RotateCcw } from 'lucide-react'
import { useState } from 'react'

import { RowActions, type RowAction } from '@/shared/components/data/RowActions'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'

import { useVehicleStatus } from '../hooks/useVehicles'
import type { Vehicle } from '../types'
import { VehicleDialog } from './VehicleDialog'

/** Actions d'une ligne de véhicule, telles que le serveur les ouvre (`actions.*`). */
export function VehicleRowActions({ vehicle }: { vehicle: Vehicle }) {
  const [editing, setEditing] = useState(false)
  const [confirming, setConfirming] = useState(false)
  const status = useVehicleStatus()

  const all: (RowAction | false)[] = [
    vehicle.actions.canUpdate && { key: 'edit', icon: Pencil, label: 'Modifier', onClick: () => setEditing(true) },
    vehicle.actions.canDeactivate && { key: 'off', icon: PowerOff, label: 'Désactiver', tone: 'danger', onClick: () => setConfirming(true) },
    vehicle.actions.canActivate && { key: 'on', icon: RotateCcw, label: 'Réactiver', onClick: () => status.mutate({ id: vehicle.id, active: true }) },
  ]

  return (
    <>
      <RowActions actions={all.filter((action): action is RowAction => action !== false)} />
      <VehicleDialog open={editing} onOpenChange={setEditing} vehicle={vehicle} />
      <ConfirmDialog
        open={confirming}
        onOpenChange={setConfirming}
        title="Désactiver ce véhicule ?"
        description={`${vehicle.code} (${vehicle.registrationNumber}) ne pourra plus être affecté à une tournée tant qu’il est inactif.`}
        confirmLabel="Désactiver"
        isPending={status.isPending}
        onConfirm={() => status.mutate({ id: vehicle.id, active: false }, { onSettled: () => setConfirming(false) })}
      />
    </>
  )
}

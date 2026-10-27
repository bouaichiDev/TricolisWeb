import { Pencil, PowerOff, RotateCcw } from 'lucide-react'
import { useState } from 'react'

import { RowActions, type RowAction } from '@/shared/components/data/RowActions'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'

import { useDriverStatus } from '../hooks/useDrivers'
import type { Driver } from '../types'
import { DriverDialog } from './DriverDialog'

/**
 * Actions d'une ligne de chauffeur, telles que le serveur les ouvre
 * (`actions.*`) : modifier, désactiver un chauffeur actif, réactiver un
 * chauffeur inactif. Désactiver demande confirmation.
 */
export function DriverRowActions({ driver }: { driver: Driver }) {
  const [editing, setEditing] = useState(false)
  const [confirming, setConfirming] = useState(false)
  const status = useDriverStatus()

  const all: (RowAction | false)[] = [
    driver.actions.canUpdate && { key: 'edit', icon: Pencil, label: 'Modifier', onClick: () => setEditing(true) },
    driver.actions.canDeactivate && { key: 'off', icon: PowerOff, label: 'Désactiver', tone: 'danger', onClick: () => setConfirming(true) },
    driver.actions.canActivate && { key: 'on', icon: RotateCcw, label: 'Réactiver', onClick: () => status.mutate({ id: driver.id, active: true }) },
  ]

  return (
    <>
      <RowActions actions={all.filter((action): action is RowAction => action !== false)} />
      <DriverDialog open={editing} onOpenChange={setEditing} driver={driver} />
      <ConfirmDialog
        open={confirming}
        onOpenChange={setConfirming}
        title="Désactiver ce chauffeur ?"
        description={`${driver.name} ne pourra plus être affecté à une tournée tant qu’il est inactif.`}
        confirmLabel="Désactiver"
        isPending={status.isPending}
        onConfirm={() => status.mutate({ id: driver.id, active: false }, { onSettled: () => setConfirming(false) })}
      />
    </>
  )
}

import { useId } from 'react'

import type { PortalRole } from '@/shared/api/types'
import { Label } from '@/shared/components/ui/label'

interface RoleSelectProps {
  roles: PortalRole[]
  value: string
  onChange: (roleId: string) => void
  label?: string
  allowEmpty?: boolean
}

/** Choix d'un rôle du portail, tel que le backend les propose. */
export function RoleSelect({ roles, value, onChange, label = 'Rôle', allowEmpty = true }: RoleSelectProps) {
  const id = useId()

  return (
    <div className="grid gap-1.5">
      <Label htmlFor={id}>{label}</Label>
      <select
        id={id}
        className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
        value={value}
        onChange={(event) => onChange(event.target.value)}
      >
        {allowEmpty && <option value="">Aucun rôle</option>}
        {roles.map((role) => (
          <option key={role.id} value={role.id}>
            {role.name}
          </option>
        ))}
      </select>
    </div>
  )
}

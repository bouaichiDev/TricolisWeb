import { useTranslation } from 'react-i18next'

import { useRoleList } from '@/modules/roles/hooks/useRoles'
import { isEditableRole } from '@/modules/roles/types/role'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { Checkbox } from '@/shared/components/ui/checkbox'
import { Label } from '@/shared/components/ui/label'

interface RoleAssignmentProps {
  selected: string[]
  onChange: (roleIds: string[]) => void
  disabled?: boolean
}

/**
 * Attribution des rôles d'un membre.
 *
 * Seuls les rôles **attribuables** sont proposés : locaux, non système, non
 * plateforme. Un rôle système porte l'intégralité des permissions de son
 * organisation ; l'attribuer transmettrait des droits que l'attribuant ne
 * détient pas nécessairement, et contournerait le plafond de délégation.
 *
 * L'API refuse ces rôles par un 422 ; les masquer évite d'y conduire.
 */
export function RoleAssignment({ selected, onChange, disabled = false }: RoleAssignmentProps) {
  const { t } = useTranslation()
  const { data, isPending, error, refetch } = useRoleList({ page: 1, perPage: 100 })

  if (isPending) return <ListSkeleton rows={4} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />

  const roles = (data?.data ?? []).filter(isEditableRole)

  if (roles.length === 0) {
    return <EmptyState title={t('users.noRoles')} description={t('users.noRolesHint')} />
  }

  const chosen = new Set(selected)

  const toggle = (roleId: string) => {
    const next = new Set(chosen)
    if (next.has(roleId)) next.delete(roleId)
    else next.add(roleId)
    onChange([...next])
  }

  return (
    <div className="grid gap-3 sm:grid-cols-2">
      {roles.map((role) => (
        <div key={role.id} className="flex items-start gap-2">
          <Checkbox
            id={`role-${role.id}`}
            checked={chosen.has(role.id)}
            disabled={disabled}
            onCheckedChange={() => toggle(role.id)}
          />
          <Label htmlFor={`role-${role.id}`} className="font-normal leading-tight">
            {role.name}
            <span className="block text-xs text-muted-foreground">{role.code}</span>
          </Label>
        </div>
      ))}
    </div>
  )
}

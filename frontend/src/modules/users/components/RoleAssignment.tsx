import { useTranslation } from 'react-i18next'

import { useRoleList } from '@/modules/roles/hooks/useRoles'
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
 * Les rôles proposés sont ceux de l'organisation active — l'API refuse un rôle
 * d'une autre organisation avec un 422, autant ne pas le proposer.
 */
export function RoleAssignment({ selected, onChange, disabled = false }: RoleAssignmentProps) {
  const { t } = useTranslation()
  const { data, isPending, error, refetch } = useRoleList({ page: 1, perPage: 100 })

  if (isPending) return <ListSkeleton rows={4} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />

  const roles = data?.data ?? []
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

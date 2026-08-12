import { useTranslation } from 'react-i18next'

import { usePermissions } from '../hooks/useRoles'
import { groupPermissionsByModule, type Permission } from '../types/role'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { Button } from '@/shared/components/ui/button'
import { Checkbox } from '@/shared/components/ui/checkbox'
import { Label } from '@/shared/components/ui/label'

interface PermissionPickerProps {
  selected: string[]
  onChange: (permissionIds: string[]) => void
  disabled?: boolean
}

/**
 * Sélection des permissions d'un rôle, groupées par module.
 *
 * Le regroupement vient du champ `module` renvoyé par l'API, jamais d'une liste
 * écrite ici : une permission ajoutée au référentiel backend apparaît alors
 * d'elle-même, dans le bon groupe.
 */
export function PermissionPicker({ selected, onChange, disabled = false }: PermissionPickerProps) {
  const { t } = useTranslation()
  const { data, isPending, error, refetch } = usePermissions()

  if (isPending) return <ListSkeleton rows={6} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />

  const groups = groupPermissionsByModule(data ?? [])
  const chosen = new Set(selected)

  const toggle = (permission: Permission) => {
    const next = new Set(chosen)
    if (next.has(permission.id)) {
      next.delete(permission.id)
    } else {
      next.add(permission.id)
    }
    onChange([...next])
  }

  const toggleModule = (permissions: Permission[], selectAll: boolean) => {
    const next = new Set(chosen)
    for (const permission of permissions) {
      if (selectAll) next.add(permission.id)
      else next.delete(permission.id)
    }
    onChange([...next])
  }

  return (
    <div className="flex flex-col gap-6">
      <p className="text-sm text-muted-foreground">
        {t('roles.permissionsCount', { count: chosen.size, total: data?.length ?? 0 })}
      </p>

      {groups.map(([module, permissions]) => {
        const allChosen = permissions.every((permission) => chosen.has(permission.id))

        return (
          <div key={module} className="flex flex-col gap-3">
            <div className="flex items-center justify-between gap-4 border-b pb-2">
              <h3 className="text-sm font-semibold">
                {t(`permissionModules.${module}`, { defaultValue: module })}
              </h3>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                disabled={disabled}
                onClick={() => toggleModule(permissions, !allChosen)}
              >
                {allChosen ? t('roles.unselectAll') : t('roles.selectAll')}
              </Button>
            </div>

            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {permissions.map((permission) => (
                <div key={permission.id} className="flex items-start gap-2">
                  <Checkbox
                    id={permission.id}
                    checked={chosen.has(permission.id)}
                    disabled={disabled}
                    onCheckedChange={() => toggle(permission)}
                  />
                  <Label htmlFor={permission.id} className="font-normal leading-tight">
                    {permission.name}
                    <span className="block text-xs text-muted-foreground">{permission.code}</span>
                  </Label>
                </div>
              ))}
            </div>
          </div>
        )
      })}
    </div>
  )
}

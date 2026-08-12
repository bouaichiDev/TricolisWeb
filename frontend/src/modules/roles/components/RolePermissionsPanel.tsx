import { useTranslation } from 'react-i18next'

import { groupPermissionsByModule, type Permission } from '../types/role'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { Badge } from '@/shared/components/ui/badge'

/** Permissions d'un rôle en lecture, groupées par module comme à la saisie. */
export function RolePermissionsPanel({ permissions }: { permissions: Permission[] }) {
  const { t } = useTranslation()

  if (permissions.length === 0) {
    return <EmptyState title={t('roles.noPermissions')} description={t('roles.noPermissionsHint')} />
  }

  return (
    <div className="flex flex-col gap-5">
      {groupPermissionsByModule(permissions).map(([module, modulePermissions]) => (
        <div key={module} className="flex flex-col gap-2">
          <h3 className="text-sm font-semibold">
            {t(`permissionModules.${module}`, { defaultValue: module })}
          </h3>
          <div className="flex flex-wrap gap-2">
            {modulePermissions.map((permission) => (
              <Badge key={permission.id} variant="secondary" className="font-normal">
                {permission.name}
              </Badge>
            ))}
          </div>
        </div>
      ))}
    </div>
  )
}

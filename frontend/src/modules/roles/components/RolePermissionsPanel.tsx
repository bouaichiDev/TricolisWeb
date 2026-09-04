import { useTranslation } from 'react-i18next'

import { groupPermissionsBySection, type Permission } from '../types/role'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { Badge } from '@/shared/components/ui/badge'

/** Permissions d'un rôle en lecture, groupées par section comme à la saisie. */
export function RolePermissionsPanel({ permissions }: { permissions: Permission[] }) {
  const { t } = useTranslation()

  if (permissions.length === 0) {
    return <EmptyState title={t('roles.noPermissions')} description={t('roles.noPermissionsHint')} />
  }

  return (
    <div className="flex flex-col gap-5">
      {groupPermissionsBySection(permissions).map(([section, sectionPermissions]) => (
        <div key={section} className="flex flex-col gap-2">
          <h3 className="text-sm font-semibold">
            {t(`menuSections.${section}`, { defaultValue: section })}
          </h3>
          <div className="flex flex-wrap gap-2">
            {sectionPermissions.map((permission) => (
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

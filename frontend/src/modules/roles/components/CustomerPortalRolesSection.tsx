import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { useRoleList } from '../hooks/useRoles'
import { SectionCard } from '@/shared/components/layout/SectionCard'

/**
 * Les rôles du portail client de l'organisation.
 *
 * Ils ne se modifient pas ici — leurs permissions sont livrées avec
 * l'application — mais c'est de leur fiche que le transporteur compose le
 * tableau de bord que verront ses clients.
 */
export function CustomerPortalRolesSection() {
  const { t } = useTranslation()
  const { data } = useRoleList({ page: 1, perPage: 50, scope: 'customer' })
  const roles = data?.data ?? []

  if (roles.length === 0) return null

  return (
    <SectionCard title={t('roles.customerPortal.title')} description={t('roles.customerPortal.description')}>
      <ul className="divide-y">
        {roles.map((role) => (
          <li key={role.id} className="flex items-center justify-between gap-3 py-2">
            <span>
              <Link to={`/roles/${role.id}`} className="font-medium text-primary hover:underline">
                {role.name}
              </Link>
              <span className="block text-xs text-muted-foreground">{role.code}</span>
            </span>
            <span className="text-sm text-muted-foreground">
              {role.permissions?.length ?? 0} {t('roles.permissions').toLowerCase()}
            </span>
          </li>
        ))}
      </ul>
    </SectionCard>
  )
}

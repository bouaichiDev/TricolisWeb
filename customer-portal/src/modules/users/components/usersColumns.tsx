import { Link } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import type { PortalUser } from '@/shared/api/types'
import type { Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Badge } from '@/shared/components/ui/badge'
import { formatDateTime } from '@/shared/utils/format'

import { displayName } from '../utils'

/** Colonnes de la liste des utilisateurs, à la façon des listes du back-office. */
export const usersColumns: Column<PortalUser>[] = [
  {
    key: 'name',
    header: 'Nom',
    cell: (member) => (
      <span className="flex flex-col">
        <Link to={paths.user(member.id)} className="text-primary font-medium hover:underline">
          {displayName(member)}
        </Link>
        <span className="text-muted-foreground text-xs">{member.user.email}</span>
      </span>
    ),
  },
  {
    key: 'roles',
    header: 'Rôles',
    hideOnMobile: true,
    cell: (member) => (
      <span className="flex flex-wrap gap-1">
        {member.isOwner && <Badge variant="secondary">Propriétaire</Badge>}
        {member.roles.map((role) => (
          <Badge key={role.id} variant="outline" className="font-normal">
            {role.name}
          </Badge>
        ))}
      </span>
    ),
  },
  { key: 'status', header: 'Statut', cell: (member) => <StatusBadge code={member.status} label={member.statusLabel} /> },
  {
    key: 'lastLogin',
    header: 'Dernière connexion',
    hideOnMobile: true,
    cell: (member) => <span className="text-muted-foreground text-sm">{formatDateTime(member.user.lastLoginAt)}</span>,
  },
]

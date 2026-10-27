import { Link } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import type { Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { formatDate } from '@/shared/utils/format'

import type { ClaimListItem } from '../types'

/** Colonnes de la liste des réclamations (§8) ; les clés de tri sont celles du backend. */
export const claimsColumns: Column<ClaimListItem>[] = [
  {
    key: 'title',
    header: 'Titre',
    sortKey: 'title',
    cell: (claim) => (
      <Link to={paths.claim(claim.id)} className="text-primary font-medium hover:underline">
        {claim.title}
      </Link>
    ),
  },
  {
    key: 'order',
    header: 'Commande',
    cell: (claim) => (claim.order ? <Link to={paths.order(claim.order.id)} className="hover:underline">{claim.order.reference}</Link> : '—'),
  },
  {
    key: 'service',
    header: 'Service',
    hideOnMobile: true,
    cell: (claim) => (claim.orderService ? `${claim.orderService.serviceNumber} — ${claim.orderService.name ?? 'Service'}` : '—'),
  },
  { key: 'claimType', header: 'Type', hideOnMobile: true, cell: (claim) => claim.claimType },
  {
    key: 'status',
    header: 'Statut',
    sortKey: 'status',
    cell: (claim) => <StatusBadge code={claim.status.code} label={claim.status.label} color={claim.status.color} />,
  },
  { key: 'createdAt', header: 'Créée le', sortKey: 'createdAt', cell: (claim) => formatDate(claim.createdAt) },
  { key: 'closedAt', header: 'Clôturée le', sortKey: 'closedAt', hideOnMobile: true, cell: (claim) => formatDate(claim.closedAt) },
]

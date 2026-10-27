import { NavLink } from 'react-router-dom'

import { usePermissions } from '@/app/auth/useAuth'
import { paths } from '@/app/router/paths'
import { cn } from '@/shared/utils/cn'

const LINKS = [
  { to: paths.stock, label: 'Vue du stock', permission: 'customer.stock.view', end: true },
  { to: paths.stockMovements, label: 'Mouvements', permission: 'customer.stock.movements.view', end: false },
  { to: paths.stockReservations, label: 'Réservations', permission: 'customer.stock.reservations.view', end: false },
  { to: paths.stockAdjustments, label: 'Demandes de correction', permission: 'customer.stock.adjustment_requests.view', end: false },
] as const

/**
 * Les rubriques du stock (§22), chacune selon sa permission.
 */
export function StockNav() {
  const { can } = usePermissions()

  return (
    <nav aria-label="Rubriques du stock" className="flex flex-wrap gap-1 border-b">
      {LINKS.filter((link) => can(link.permission)).map((link) => (
        <NavLink
          key={link.to}
          to={link.to}
          end={link.end}
          className={({ isActive }) =>
            cn('-mb-px border-b-2 px-3 py-2 text-sm', isActive ? 'border-primary text-foreground font-medium' : 'text-muted-foreground hover:text-foreground border-transparent')
          }
        >
          {link.label}
        </NavLink>
      ))}
    </nav>
  )
}

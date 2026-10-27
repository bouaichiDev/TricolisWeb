import {
  Boxes,
  CalendarDays,
  ClipboardList,
  LayoutDashboard,
  MessageSquareWarning,
  Package,
  PlugZap,
  UserCircle,
  Users,
  type LucideIcon,
} from 'lucide-react'

import { paths } from '@/app/router/paths'

export interface NavItem {
  label: string
  icon: LucideIcon
  /** Absent : le module n'est pas encore livré, l'entrée est annoncée mais inactive. */
  to?: string
  /** Toutes requises pour afficher l'entrée. */
  permissions: readonly string[]
  /** Phase de livraison, affichée tant que le module n'existe pas. */
  phase?: string
}

/**
 * Structure du portail (§13).
 *
 * Une entrée n'apparaît que si le membre détient ses permissions : un
 * gestionnaire de stock ne voit pas « Commandes », un lecteur ne voit pas
 * « Utilisateurs ». Les modules à venir sont montrés, désactivés, à ceux qui
 * en auront l'usage — le client sait ce qui arrive, sans lien mort.
 *
 * Le suivi (Phase 3) n'a pas d'entrée propre : il se suit commande par
 * commande, depuis sa fiche ou le calendrier (`/orders/:id/tracking`).
 */
export const navigation: readonly NavItem[] = [
  { label: 'Tableau de bord', icon: LayoutDashboard, to: paths.dashboard, permissions: [] },
  { label: 'Commandes', icon: ClipboardList, to: paths.orders, permissions: ['customer.orders.view'] },
  { label: 'Calendrier', icon: CalendarDays, to: paths.calendar, permissions: ['customer.calendar.view'] },
  { label: 'Réclamations', icon: MessageSquareWarning, to: paths.claims, permissions: ['customer.claims.view'] },
  { label: 'Catalogue', icon: Package, to: paths.catalog, permissions: ['customer.catalog.view', 'customer.articles.view'] },
  { label: 'Stock', icon: Boxes, to: paths.stock, permissions: ['customer.stock.view'] },
  { label: 'Intégrations', icon: PlugZap, to: paths.integrations, permissions: ['customer.integrations.view'] },
  { label: 'Utilisateurs', icon: Users, to: paths.users, permissions: ['customer.users.view'] },
  { label: 'Profil', icon: UserCircle, to: paths.profile, permissions: [] },
]

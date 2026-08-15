import {
  Boxes,
  Building2,
  ClipboardList,
  LayoutDashboard,
  Network,
  Settings,
  Shield,
  Users,
  Warehouse,
  type LucideIcon,
} from 'lucide-react'

/**
 * Correspondance entre le nom d'icône du catalogue et le composant React.
 *
 * Une icône est un composant, pas une donnée : le backend en stocke le **nom**,
 * et cette table le résout. C'est la part du menu qui ne peut pas quitter le
 * code, et l'une des raisons pour lesquelles le catalogue lui-même y reste.
 *
 * Un nom inconnu retombe sur une icône neutre plutôt que de faire échouer le
 * rendu : une entrée sans icône reste utilisable, une barre latérale blanche
 * ne l'est pas.
 */
const ICONS: Record<string, LucideIcon> = {
  LayoutDashboard,
  Building2,
  Boxes,
  Network,
  Warehouse,
  Settings,
  Users,
  Shield,
  ClipboardList,
}

export function menuIcon(name: string): LucideIcon {
  return ICONS[name] ?? Boxes
}

/** Noms connus, pour le test qui vérifie que le catalogue n'en cite aucun autre. */
export const KNOWN_ICONS = Object.keys(ICONS)

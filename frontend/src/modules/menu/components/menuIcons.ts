import {
  ArrowRightLeft,
  BarChart3,
  Bell,
  Bookmark,
  Boxes,
  Building2,
  Calculator,
  CalendarRange,
  ClipboardList,
  Clock,
  CreditCard,
  FileInput,
  FileOutput,
  FileText,
  Flag,
  Folder,
  Globe,
  HandCoins,
  History,
  Home,
  IdCard,
  Inbox,
  KeyRound,
  Layers,
  LayoutDashboard,
  List as ListIcon,
  Lock,
  Mail,
  Map as MapIcon,
  MapPin,
  MessageSquareWarning,
  Network,
  Package,
  Phone,
  PieChart,
  Plug,
  ReceiptText,
  Route,
  Search,
  Send,
  Settings,
  Shield,
  Star,
  Tags,
  Target,
  Truck,
  Users,
  Variable,
  Wallet,
  Warehouse,
  Workflow,
  Wrench,
  Zap,
  type LucideIcon,
} from 'lucide-react'

/**
 * Correspondance entre le nom d'icône du catalogue et le composant React.
 *
 * Une icône est un composant, pas une donnée : le backend en stocke le **nom**,
 * et cette table le résout. C'est la part du menu qui ne peut pas quitter le
 * code, et l'une des raisons pour lesquelles le catalogue lui-même y reste.
 *
 * Cette table est aussi ce que l'écran de réglage propose à une organisation
 * qui veut changer l'icône d'une entrée. Elle est donc **plus large que le
 * catalogue** : elle porte les icônes utilisées par défaut, plus un fonds
 * générique — sans quoi le sélecteur ne proposerait que ce qui est déjà pris.
 * `App\Shared\Menu\MenuIcons` en est le miroir côté backend, qui refuse un nom
 * absent d'ici : accepté, il retomberait sur l'icône neutre et
 * l'administrateur croirait avoir choisi.
 *
 * Un nom inconnu retombe sur une icône neutre plutôt que de faire échouer le
 * rendu : une entrée sans icône reste utilisable, une barre latérale blanche
 * ne l'est pas.
 */
const ICONS: Record<string, LucideIcon> = {
  ArrowRightLeft,
  BarChart3,
  Bell,
  Bookmark,
  Boxes,
  Building2,
  Calculator,
  CalendarRange,
  ClipboardList,
  Clock,
  CreditCard,
  FileInput,
  FileOutput,
  FileText,
  Flag,
  Folder,
  Globe,
  HandCoins,
  History,
  Home,
  IdCard,
  Inbox,
  KeyRound,
  Layers,
  LayoutDashboard,
  List: ListIcon,
  Lock,
  Mail,
  Map: MapIcon,
  MapPin,
  MessageSquareWarning,
  Network,
  Package,
  Phone,
  PieChart,
  Plug,
  ReceiptText,
  Route,
  Search,
  Send,
  Settings,
  Shield,
  Star,
  Tags,
  Target,
  Truck,
  Users,
  Variable,
  Wallet,
  Warehouse,
  Workflow,
  Wrench,
  Zap,
}

export function menuIcon(name: string): LucideIcon {
  return ICONS[name] ?? Boxes
}

/**
 * Noms proposés au sélecteur d'icône, et confrontés au catalogue par le test
 * qui vérifie qu'aucune entrée n'en cite un autre.
 */
export const KNOWN_ICONS = Object.keys(ICONS)

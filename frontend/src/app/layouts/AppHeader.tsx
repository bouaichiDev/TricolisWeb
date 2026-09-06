import { Menu } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Breadcrumbs } from './Breadcrumbs'
import { OrganizationSwitcher } from './OrganizationSwitcher'
import { UserMenu } from './UserMenu'
import { NotificationBell } from '@/modules/notifications/components/NotificationBell'
import { Button } from '@/shared/components/ui/button'

interface AppHeaderProps {
  onOpenMobileMenu: () => void
}

/**
 * Bandeau supérieur.
 *
 * **Les notifications y sont désormais.** Elles ont longtemps manqué, et pour
 * une bonne raison : aucun endpoint ne les alimentait, et une cloche inerte est
 * un faux. `GET /notifications` en sert maintenant deux moitiés que le domaine
 * distingue depuis la Phase 9 — les notifications internes, qui sont des
 * `order_communications` de canal `internal_notification`, et les envois
 * externes qui ont échoué.
 *
 * La recherche globale reste absente, et pour la même raison qu'avant : il
 * n'existe aucune route de recherche transverse.
 */
export function AppHeader({ onOpenMobileMenu }: AppHeaderProps) {
  const { t } = useTranslation()

  return (
    <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b bg-card px-4 lg:px-6">
      <Button
        variant="ghost"
        size="icon"
        className="lg:hidden"
        onClick={onOpenMobileMenu}
        aria-label={t('nav.main')}
      >
        <Menu className="size-5" aria-hidden />
      </Button>

      <div className="min-w-0 flex-1">
        <Breadcrumbs />
      </div>

      <NotificationBell />
      <OrganizationSwitcher />
      <UserMenu />
    </header>
  )
}

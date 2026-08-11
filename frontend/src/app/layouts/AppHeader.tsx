import { Menu } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Breadcrumbs } from './Breadcrumbs'
import { OrganizationSwitcher } from './OrganizationSwitcher'
import { UserMenu } from './UserMenu'
import { Button } from '@/shared/components/ui/button'

interface AppHeaderProps {
  onOpenMobileMenu: () => void
}

/**
 * Bandeau supérieur.
 *
 * La recherche globale et les notifications figurent dans les maquettes, mais
 * **aucun endpoint ne les alimente** : il n'existe ni route de recherche
 * transverse ni route de notification dans les 308 de l'API. Les afficher
 * inertes serait un faux ; ils sont donc absents jusqu'à ce que le backend les
 * expose. Voir la section « API manquantes » du rapport de phase.
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

      <OrganizationSwitcher />
      <UserMenu />
    </header>
  )
}

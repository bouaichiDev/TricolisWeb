import { Menu, Truck } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, Outlet } from 'react-router-dom'

import { AppSidebar } from './AppSidebar'
import { OrganizationSwitcher } from './OrganizationSwitcher'
import { UserMenu } from './UserMenu'
import { Button } from '@/shared/components/ui/button'
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/shared/components/ui/sheet'

/**
 * Ossature de l'application.
 *
 * Une seule barre latérale est écrite : au-delà de `lg` elle est fixe dans la
 * colonne de gauche, en dessous elle est montée dans un tiroir. Dupliquer le
 * composant pour chaque taille d'écran aurait imposé de maintenir deux menus.
 */
export function AppLayout() {
  const { t } = useTranslation()
  const [mobileOpen, setMobileOpen] = useState(false)

  return (
    <div className="min-h-screen bg-background">
      <header className="sticky top-0 z-40 flex h-14 items-center gap-3 border-b bg-card px-4">
        <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
          <SheetTrigger asChild>
            <Button variant="ghost" size="icon" className="lg:hidden" aria-label={t('nav.dashboard')}>
              <Menu className="size-5" aria-hidden />
            </Button>
          </SheetTrigger>

          <SheetContent side="left" className="w-72 bg-sidebar p-0">
            <SheetTitle className="sr-only">{t('app.name')}</SheetTitle>
            <AppSidebar onNavigate={() => setMobileOpen(false)} />
          </SheetContent>
        </Sheet>

        <Link to="/dashboard" className="flex shrink-0 items-center gap-2 font-semibold">
          <Truck className="size-5 text-primary" aria-hidden />
          <span className="hidden sm:inline">{t('app.name')}</span>
        </Link>

        <div className="min-w-0 flex-1">
          <OrganizationSwitcher />
        </div>

        <UserMenu />
      </header>

      <div className="flex">
        <aside className="sticky top-14 hidden h-[calc(100vh-3.5rem)] w-64 shrink-0 border-r bg-sidebar lg:block">
          <AppSidebar />
        </aside>

        <main className="min-w-0 flex-1 p-4 lg:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

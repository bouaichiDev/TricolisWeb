import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Outlet } from 'react-router-dom'

import { AppHeader } from './AppHeader'
import { AppSidebar } from './AppSidebar'
import { Sheet, SheetContent, SheetTitle } from '@/shared/components/ui/sheet'

/**
 * Ossature de l'application.
 *
 * Une seule barre latérale est écrite : fixe dans la colonne de gauche au-delà
 * de `lg`, montée dans un tiroir en dessous. Dupliquer le composant par taille
 * d'écran aurait imposé de maintenir deux menus, donc de les laisser diverger.
 */
export function AppLayout() {
  const { t } = useTranslation()
  const [mobileOpen, setMobileOpen] = useState(false)

  return (
    <div className="flex min-h-screen bg-muted/30">
      <aside className="sticky top-0 hidden h-screen w-64 shrink-0 lg:block">
        <AppSidebar />
      </aside>

      <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
        <SheetContent side="left" className="w-72 border-0 bg-sidebar p-0">
          <SheetTitle className="sr-only">{t('nav.main')}</SheetTitle>
          <AppSidebar onNavigate={() => setMobileOpen(false)} />
        </SheetContent>
      </Sheet>

      <div className="flex min-w-0 flex-1 flex-col">
        <AppHeader onOpenMobileMenu={() => setMobileOpen(true)} />

        <main className="min-w-0 flex-1 p-4 lg:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

import { Suspense, useState } from 'react'
import { Outlet } from 'react-router-dom'

import { InlineLoader } from '@/shared/components/feedback/States'
import { Sheet, SheetContent, SheetTitle } from '@/shared/components/ui/sheet'

import { PortalHeader } from './PortalHeader'
import { PortalSidebar } from './PortalSidebar'

/**
 * Ossature du back-office, reprise telle quelle : barre latérale fixe au-delà
 * de `lg`, tiroir en dessous, fond légèrement grisé sous les cartes.
 */
export function PortalLayout() {
  const [menuOpen, setMenuOpen] = useState(false)

  return (
    <div className="bg-muted/30 flex min-h-screen">
      <aside className="sticky top-0 hidden h-screen w-64 shrink-0 lg:block">
        <PortalSidebar />
      </aside>
      <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
        <SheetContent side="left" className="bg-sidebar w-72 border-0 p-0">
          <SheetTitle className="sr-only">Navigation principale</SheetTitle>
          <PortalSidebar onNavigate={() => setMenuOpen(false)} />
        </SheetContent>
      </Sheet>
      <div className="flex min-w-0 flex-1 flex-col">
        <PortalHeader onOpenMenu={() => setMenuOpen(true)} />
        <main className="min-w-0 flex-1 p-4 lg:p-6">
          <Suspense fallback={<InlineLoader />}>
            <Outlet />
          </Suspense>
        </main>
      </div>
    </div>
  )
}

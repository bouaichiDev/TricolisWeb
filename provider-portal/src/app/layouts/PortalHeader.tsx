import { Menu } from 'lucide-react'

import { useAuth } from '@/app/auth/useAuth'
import { Button } from '@/shared/components/ui/button'

import { Breadcrumbs } from './Breadcrumbs'
import { UserMenu } from './UserMenu'

/**
 * Bandeau du back-office : fil d'Ariane, fournisseur courant (à la place de
 * l'organisation), menu du compte.
 */
export function PortalHeader({ onOpenMenu }: { onOpenMenu: () => void }) {
  const { context } = useAuth()

  return (
    <header className="bg-card sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b px-4 lg:px-6">
      <Button variant="ghost" size="icon" className="lg:hidden" onClick={onOpenMenu} aria-label="Ouvrir le menu">
        <Menu className="size-5" aria-hidden />
      </Button>
      <div className="min-w-0 flex-1">
        <Breadcrumbs />
      </div>
      {context && (
        <div className="flex min-w-0 items-center gap-2 px-2">
          <span className="truncate text-sm font-medium" data-testid="current-provider">
            {context.provider.name}
          </span>
        </div>
      )}
      <UserMenu />
    </header>
  )
}

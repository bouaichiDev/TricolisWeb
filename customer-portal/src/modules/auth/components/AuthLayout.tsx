import { Lock } from 'lucide-react'
import type { ReactNode } from 'react'

import { AuthBrand } from './AuthBrand'
import { AuthShowcase } from './AuthShowcase'

interface AuthLayoutProps {
  title: string
  subtitle?: string
  children: ReactNode
  /** Liens de bas de formulaire. */
  aside?: ReactNode
}

/**
 * L'écran scindé des pages publiques, repris du back-office.
 *
 * La page ne défile jamais (`h-dvh`) : sur un écran court, c'est la colonne du
 * formulaire qui défile. Le panneau de marque n'apparaît qu'à partir de `lg`.
 */
export function AuthLayout({ title, subtitle, children, aside }: AuthLayoutProps) {
  return (
    <main className="bg-background flex h-dvh overflow-hidden">
      <section className="flex flex-1 flex-col overflow-y-auto px-6 py-6 sm:px-10 lg:px-14 xl:px-20 [@media(min-height:760px)]:py-10">
        <div className="mx-auto flex min-h-full w-full max-w-md flex-col">
          <header className="mb-6 flex shrink-0 items-center [@media(min-height:760px)]:mb-10">
            <AuthBrand />
          </header>

          <div className="my-auto py-2">
            <div className="mb-6">
              <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">{title}</h1>
              {subtitle && <p className="text-muted-foreground mt-2 text-sm leading-relaxed">{subtitle}</p>}
            </div>

            {children}

            {aside}
          </div>

          <footer className="border-border text-muted-foreground mt-6 flex shrink-0 flex-col items-center justify-between gap-2 border-t pt-5 text-xs sm:flex-row [@media(max-height:640px)]:hidden">
            <p className="flex items-center gap-1.5">
              <Lock className="size-3.5" aria-hidden />
              Connexion sécurisée et chiffrée
            </p>
            <p>Besoin d’aide ? Contactez votre administrateur.</p>
          </footer>
        </div>
      </section>

      <AuthShowcase />
    </main>
  )
}

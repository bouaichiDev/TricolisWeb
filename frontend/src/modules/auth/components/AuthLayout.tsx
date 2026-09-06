import { Lock } from 'lucide-react'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

import { AuthBrand } from '@/modules/auth/components/AuthBrand'
import { AuthShowcase } from '@/modules/auth/components/AuthShowcase'

interface AuthLayoutProps {
  title: string
  subtitle: string
  children: ReactNode
  /** Liens de bas de formulaire — retour, demande d'accès. */
  aside?: ReactNode
}

/**
 * L'écran scindé que partagent connexion, mot de passe oublié et demande d'accès.
 *
 * **La page ne défile jamais.** `h-dvh` et `overflow-hidden` la clouent à la
 * hauteur réelle de la fenêtre — `dvh` et non `vh` parce que la barre d'adresse
 * mobile ment sur la seconde. Ce qui pourrait déborder défile *à l'intérieur*
 * de la colonne de gauche : sur un écran court, c'est le formulaire qu'on veut
 * atteindre, jamais la page entière qui doit glisser sous les pieds.
 *
 * Le panneau de droite ne s'affiche qu'à partir de `lg` : sur un téléphone il
 * repousserait le formulaire hors de vue pour ne montrer que du décor.
 *
 * Sous 640 pixels de haut — un téléphone couché — le pied de page s'efface. Il
 * rassure, il n'agit pas ; le formulaire, lui, est ce pour quoi on est venu.
 */
export function AuthLayout({ title, subtitle, children, aside }: AuthLayoutProps) {
  const { t } = useTranslation()

  return (
    <main className="flex h-dvh overflow-hidden bg-background">
      <section className="flex flex-1 flex-col overflow-y-auto px-6 py-6 sm:px-10 lg:px-14 xl:px-20 [@media(min-height:760px)]:py-10">
        <div className="mx-auto flex min-h-full w-full max-w-md flex-col">
          <header className="mb-6 flex shrink-0 items-center [@media(min-height:760px)]:mb-10">
            <AuthBrand />
          </header>

          <div className="my-auto py-2">
            <div className="mb-6">
              <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">{title}</h1>
              <p className="mt-2 text-sm leading-relaxed text-muted-foreground">{subtitle}</p>
            </div>

            {children}

            {aside}
          </div>

          <footer className="mt-6 flex shrink-0 flex-col items-center justify-between gap-2 border-t border-border pt-5 text-xs text-muted-foreground [@media(max-height:640px)]:hidden sm:flex-row">
            <p className="flex items-center gap-1.5">
              <Lock className="size-3.5" aria-hidden />
              {t('auth.secureConnection')}
            </p>
            <p>{t('auth.needHelp')}</p>
          </footer>
        </div>
      </section>

      <AuthShowcase />
    </main>
  )
}

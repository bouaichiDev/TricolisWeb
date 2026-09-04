import { ArrowUpRight } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'

import { cn } from '@/shared/utils/cn'

interface WidgetCardProps {
  title: string
  /** Écran que la carte ouvre, quand il en existe un. */
  to?: string | null
  /** Teinte d'alerte, réservée aux compteurs qui appellent une action. */
  tone?: 'default' | 'attention'
  children: ReactNode
}

/**
 * L'enveloppe commune à tous les widgets.
 *
 * Elle porte le titre, la teinte et le lien éventuel — trois choses que chacun
 * des cinq types aurait sinon réécrites, avec cinq bordures légèrement
 * différentes.
 *
 * **Toutes les cartes ne sont pas cliquables**, et c'est voulu : un compteur de
 * services n'a pas d'écran où mener — les services se lisent dans leur
 * commande. Inventer une destination aurait donné une carte qui promet plus
 * qu'elle ne tient. Sans `to`, la carte reste un `div` : pas de curseur en
 * main, pas de survol, rien qui suggère un clic.
 */
export function WidgetCard({ title, to, tone = 'default', children }: WidgetCardProps) {
  const body = (
    <>
      <div className="flex items-start justify-between gap-2">
        <span className="text-sm font-medium text-muted-foreground">{title}</span>
        {to ? <ArrowUpRight className="size-4 shrink-0 text-muted-foreground/60" aria-hidden /> : null}
      </div>
      {children}
    </>
  )

  const className = cn(
    'flex h-full flex-col gap-3 rounded-lg border bg-card p-5',
    tone === 'attention' && 'border-warning/40 bg-warning/5',
    to && 'transition-colors hover:border-primary/40 hover:bg-accent/40',
  )

  if (!to) {
    return <div className={className}>{body}</div>
  }

  return (
    <Link to={to} className={className}>
      {body}
    </Link>
  )
}

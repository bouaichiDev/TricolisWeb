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
  /**
   * Le corps porte ses **propres** liens — une colonne, une part, une ligne.
   *
   * La carte cesse alors d'être un lien, et le titre en devient un. Ce n'est
   * pas une préférence de rendu : un `<a>` dans un `<a>` est du HTML invalide,
   * que le navigateur défait comme il peut, et le clic sur la part serait parti
   * vers la destination de la carte — la liste entière, c'est-à-dire
   * exactement ce que le forage corrige.
   */
  interactive?: boolean
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
 *
 * **Les graphes, eux, ne sont jamais cliquables en entier.** Sur une carte où
 * chaque part mène à sa propre liste, un lien posé sur le tout dirait qu'on
 * peut cliquer n'importe où pour le même résultat — et l'endroit visé serait le
 * seul à ne pas compter. Le titre reste le chemin vers la liste entière, ce qui
 * laisse les deux gestes disponibles sans les confondre.
 */
export function WidgetCard({
  title,
  to,
  tone = 'default',
  interactive = false,
  children,
}: WidgetCardProps) {
  const linked = Boolean(to)
  const cardIsLink = linked && ! interactive

  const heading = (
    <div className="flex items-start justify-between gap-2">
      <span className="text-sm font-medium text-muted-foreground">{title}</span>
      {linked ? <ArrowUpRight className="size-4 shrink-0 text-muted-foreground/60" aria-hidden /> : null}
    </div>
  )

  const className = cn(
    'flex h-full flex-col gap-3 rounded-lg border bg-card p-5',
    tone === 'attention' && 'border-warning/40 bg-warning/5',
    cardIsLink && 'transition-colors hover:border-primary/40 hover:bg-accent/40',
  )

  if (cardIsLink) {
    return (
      <Link to={to as string} className={className}>
        {heading}
        {children}
      </Link>
    )
  }

  return (
    <div className={className}>
      {linked ? (
        <Link
          to={to as string}
          className="rounded-md transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          {heading}
        </Link>
      ) : (
        heading
      )}
      {children}
    </div>
  )
}

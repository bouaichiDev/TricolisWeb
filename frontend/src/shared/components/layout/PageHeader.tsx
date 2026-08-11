import type { ReactNode } from 'react'

interface PageHeaderProps {
  title: string
  description?: string
  /** Boutons d'action, alignés à droite sur grand écran. */
  actions?: ReactNode
}

/**
 * En-tête de page.
 *
 * Sur mobile, les actions passent sous le titre plutôt que de le comprimer :
 * un bouton « Nouveau client » à côté d'un titre tronqué ne sert personne.
 */
export function PageHeader({ title, description, actions }: PageHeaderProps) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div className="min-w-0">
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {description ? (
          <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        ) : null}
      </div>

      {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
    </div>
  )
}

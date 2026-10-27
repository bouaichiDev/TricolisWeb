import type { ReactNode } from 'react'

interface EntityHeaderProps {
  title: string
  subtitle?: string
  /** Badge de statut, déjà rendu. */
  status?: ReactNode
  actions?: ReactNode
}

/** En-tête d'une fiche : titre, statut, sous-titre, actions — comme au back-office. */
export function EntityHeader({ title, subtitle, status, actions }: EntityHeaderProps) {
  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
          {status}
        </div>
        {subtitle ? <p className="text-muted-foreground mt-1 text-sm">{subtitle}</p> : null}
      </div>
      {actions ? <div className="flex shrink-0 flex-wrap gap-2">{actions}</div> : null}
    </div>
  )
}

import type { ReactNode } from 'react'

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/shared/components/ui/card'

interface SectionCardProps {
  title: string
  description?: string
  actions?: ReactNode
  children: ReactNode
}

/** Bloc de contenu titre, unite visuelle des fiches et des formulaires. */
export function SectionCard({ title, description, actions, children }: SectionCardProps) {
  return (
    <Card>
      <CardHeader className={actions ? 'flex-row items-start justify-between gap-4' : undefined}>
        <div className="min-w-0">
          <CardTitle className="text-base">{title}</CardTitle>
          {description ? <CardDescription>{description}</CardDescription> : null}
        </div>
        {actions ? <div className="flex shrink-0 gap-2">{actions}</div> : null}
      </CardHeader>
      <CardContent>{children}</CardContent>
    </Card>
  )
}

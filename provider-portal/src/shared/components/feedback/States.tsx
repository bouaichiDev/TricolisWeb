import { AlertCircle, Inbox, Loader2, type LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'

import { Button } from '@/shared/components/ui/button'

export function FullPageLoader({ label = 'Chargement…' }: { label?: string }) {
  return (
    <div className="flex min-h-screen items-center justify-center" role="status" aria-live="polite">
      <Loader2 className="text-muted-foreground size-6 animate-spin" aria-hidden />
      <span className="sr-only">{label}</span>
    </div>
  )
}

export function InlineLoader({ label = 'Chargement…' }: { label?: string }) {
  return (
    <div className="text-muted-foreground flex items-center gap-2 py-8 text-sm" role="status">
      <Loader2 className="size-4 animate-spin" aria-hidden />
      {label}
    </div>
  )
}

interface ErrorStateProps {
  title?: string
  message: string
  onRetry?: () => void
}

/** Même carte d'erreur que le back-office. */
export function ErrorState({ title = 'Impossible d’afficher cette page', message, onRetry }: ErrorStateProps) {
  return (
    <div role="alert" className="bg-card flex flex-col items-center gap-3 rounded-lg border py-12 text-center">
      <AlertCircle className="text-destructive size-8" aria-hidden />
      <div>
        <p className="font-medium">{title}</p>
        <p className="text-muted-foreground mt-1 max-w-md text-sm">{message}</p>
      </div>
      {onRetry && (
        <Button variant="outline" onClick={onRetry}>
          Réessayer
        </Button>
      )}
    </div>
  )
}

interface EmptyStateProps {
  title: string
  icon?: LucideIcon
  action?: ReactNode
  children?: ReactNode
}

/** Même encadré pointillé que le back-office. */
export function EmptyState({ title, icon: Icon = Inbox, action, children }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed py-12 text-center">
      <Icon className="text-muted-foreground size-8" aria-hidden />
      <div>
        <p className="font-medium">{title}</p>
        {children && <div className="text-muted-foreground mt-1 text-sm">{children}</div>}
      </div>
      {action}
    </div>
  )
}

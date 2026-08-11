import { Loader2 } from 'lucide-react'

/**
 * Attente bloquante, réservée aux cas où rien d'autre ne peut être affiché :
 * vérification de session au démarrage, et rien d'autre. Les chargements de
 * page passent par des squelettes, qui gardent la mise en page stable.
 */
export function FullPageLoader({ label }: { label?: string }) {
  return (
    <div
      className="flex min-h-screen items-center justify-center gap-3 text-muted-foreground"
      role="status"
      aria-live="polite"
    >
      <Loader2 className="size-5 animate-spin" aria-hidden />
      <span className="text-sm">{label ?? 'Chargement…'}</span>
    </div>
  )
}

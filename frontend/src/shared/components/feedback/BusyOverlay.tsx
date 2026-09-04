import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

interface BusyOverlayProps {
  active: boolean
  /** Ce que le système est en train de faire, dit à l'utilisateur. */
  label?: string
}

/**
 * Bloque une zone le temps qu'une opération aboutisse ou échoue.
 *
 * **Planifier n'est pas instantané.** Le serveur regroupe les arrêts, promeut le
 * chargement au dépôt, recalcule les totaux et lance l'itinéraire : une seconde
 * ou deux pendant lesquelles un écran muet invite à recliquer, et un second clic
 * verse la commande deux fois — ou la retire de ce qu'on venait d'y mettre.
 *
 * Le voile couvre la zone plutôt que la page entière : ce qui est bloqué est ce
 * qui va changer, et le reste de l'application n'a pas à s'arrêter.
 *
 * `aria-live` le dit à qui n'a pas l'écran sous les yeux, et le voile capture
 * les clics : désactiver les boutons un à un en laisserait toujours un.
 *
 * Le parent doit être positionné — `relative` — sans quoi le voile se placerait
 * par rapport à la page.
 */
export function BusyOverlay({ active, label }: BusyOverlayProps) {
  const { t } = useTranslation()

  if (!active) return null

  return (
    <div
      role="status"
      aria-live="polite"
      className="absolute inset-0 z-30 flex items-center justify-center rounded-lg bg-background/70"
    >
      <span className="flex items-center gap-2 rounded-md border bg-card px-3 py-2 shadow-sm">
        <Loader2 className="size-4 animate-spin text-primary" aria-hidden />
        <span className="text-sm font-medium">{label ?? t('common.working')}</span>
      </span>
    </div>
  )
}

import { useTranslation } from 'react-i18next'

import type { RoleDashboardWidget } from '../types/dashboard'
import { cn } from '@/shared/utils/cn'

const SPANS: Record<string, string> = {
  small: 'md:col-span-3',
  medium: 'md:col-span-6',
  large: 'md:col-span-6 lg:col-span-8',
  full: 'md:col-span-6 lg:col-span-12',
}

/**
 * À quoi ressemblera le tableau de bord de ce rôle.
 *
 * **Sans aucune donnée réelle.** L'aperçu ne demande rien au serveur : il
 * dessine des tuiles vides aux bonnes places, d'après les métadonnées que
 * l'écran de réglage a déjà reçues. C'est une précaution, pas une économie —
 * afficher ici les chiffres du rôle qu'on configure aurait montré à
 * l'administrateur des données qu'il n'a peut-être pas le droit de lire, alors
 * qu'il a seulement le droit de **ranger** ce que d'autres liront.
 *
 * Ce qu'il montre est donc exact sur la forme — quels widgets, dans quel ordre,
 * de quelle taille — et muet sur le fond. C'est précisément ce qu'on vient y
 * vérifier.
 */
export function RoleDashboardPreview({ active }: { active: RoleDashboardWidget[] }) {
  const { t } = useTranslation()

  if (active.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('dashboardSettings.previewEmpty')}</p>
  }

  return (
    <div className="grid grid-cols-1 gap-3 rounded-lg border border-dashed bg-muted/30 p-4 md:grid-cols-6 lg:grid-cols-12">
      {active.map((widget) => (
        <div
          key={widget.key}
          className={cn(
            'flex h-20 flex-col justify-between rounded-md border bg-card p-3',
            SPANS[widget.size] ?? SPANS.small,
          )}
        >
          <span className="truncate text-xs font-medium text-muted-foreground">
            {t(widget.labelKey)}
          </span>
          {/* Un trait, pas un nombre : un chiffre d'exemple finirait par être
              lu comme une valeur, et « 42 » sur une carte de facturation n'est
              pas une plaisanterie qu'on veut voir en réunion. */}
          <span className="h-5 w-12 rounded bg-muted" aria-hidden />
        </div>
      ))}
    </div>
  )
}

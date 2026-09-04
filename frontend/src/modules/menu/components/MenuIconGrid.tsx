import { useTranslation } from 'react-i18next'

import { KNOWN_ICONS, menuIcon } from './menuIcons'
import { cn } from '@/shared/utils/cn'

interface MenuIconGridProps {
  value: string
  onChange: (icon: string) => void
}

/**
 * Choix de l'icône d'une entrée, parmi celles que le frontend sait rendre.
 *
 * La liste est fermée, et c'est délibéré : une icône est un composant React,
 * pas une donnée. Un champ libre laisserait saisir un nom que `menuIcons.ts`
 * ignore, l'entrée retomberait sur l'icône neutre, et l'administrateur croirait
 * avoir choisi. Les proposer toutes évite d'avoir à deviner l'orthographe.
 */
export function MenuIconGrid({ value, onChange }: MenuIconGridProps) {
  const { t } = useTranslation()

  return (
    <div
      role="radiogroup"
      aria-label={t('menu.icon')}
      className="grid max-h-56 grid-cols-8 gap-1 overflow-y-auto rounded-md border p-2"
    >
      {KNOWN_ICONS.map((name) => {
        const Icon = menuIcon(name)
        const selected = name === value

        return (
          <button
            key={name}
            type="button"
            role="radio"
            aria-checked={selected}
            aria-label={name}
            title={name}
            onClick={() => onChange(name)}
            className={cn(
              'flex size-9 items-center justify-center rounded-md border transition-colors',
              selected
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-transparent text-muted-foreground hover:bg-accent hover:text-foreground',
            )}
          >
            <Icon className="size-4" aria-hidden />
          </button>
        )
      })}
    </div>
  )
}

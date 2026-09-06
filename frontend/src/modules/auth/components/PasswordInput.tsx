import { Eye, EyeOff, Lock } from 'lucide-react'
import { type ComponentProps, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Input } from '@/shared/components/ui/input'
import { cn } from '@/shared/utils/cn'

/**
 * Le champ mot de passe des écrans d'authentification.
 *
 * L'œil qui dévoile la saisie n'est pas un ornement : sur un mot de passe long
 * tapé au clavier mobile, il évite le troisième essai raté. Son libellé
 * accessible évite volontairement les mots « mot de passe », qui feraient de ce
 * bouton un second candidat pour `getByLabel('Mot de passe')` — le champ doit
 * rester le seul.
 */
export function PasswordInput({
  className,
  ...props
}: ComponentProps<typeof Input>) {
  const { t } = useTranslation()
  const [revealed, setRevealed] = useState(false)

  const Icon = revealed ? EyeOff : Eye

  return (
    <div className="relative">
      <Lock
        className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
        aria-hidden
      />

      <Input
        type={revealed ? 'text' : 'password'}
        className={cn('h-12 rounded-xl bg-muted/40 pr-11 pl-11', className)}
        {...props}
      />

      <button
        type="button"
        onClick={() => setRevealed((shown) => !shown)}
        aria-label={revealed ? t('auth.hideEntry') : t('auth.revealEntry')}
        className="absolute top-1/2 right-1 flex size-9 -translate-y-1/2 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
      >
        <Icon className="size-4" aria-hidden />
      </button>
    </div>
  )
}

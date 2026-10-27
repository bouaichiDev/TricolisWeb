import { Eye, EyeOff, Lock } from 'lucide-react'
import { useState, type ComponentProps } from 'react'

import { Input } from '@/shared/components/ui/input'
import { cn } from '@/shared/utils/cn'

/**
 * Champ mot de passe avec l'œil qui dévoile la saisie, comme au back-office.
 *
 * Le libellé du bouton évite « mot de passe » : `getByLabel('Mot de passe')`
 * doit désigner le champ seul.
 */
export function PasswordInput({ className, ...props }: ComponentProps<typeof Input>) {
  const [revealed, setRevealed] = useState(false)
  const Icon = revealed ? EyeOff : Eye

  return (
    <div className="relative">
      <Lock
        className="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2"
        aria-hidden
      />
      <Input
        type={revealed ? 'text' : 'password'}
        className={cn('bg-muted/40 h-12 rounded-xl pr-11 pl-11', className)}
        {...props}
      />
      <button
        type="button"
        onClick={() => setRevealed((shown) => !shown)}
        aria-label={revealed ? 'Masquer la saisie' : 'Afficher la saisie'}
        className="text-muted-foreground hover:text-foreground focus-visible:ring-ring/50 absolute top-1/2 right-1 flex size-9 -translate-y-1/2 items-center justify-center rounded-lg transition-colors focus-visible:ring-[3px] focus-visible:outline-none"
      >
        <Icon className="size-4" aria-hidden />
      </button>
    </div>
  )
}

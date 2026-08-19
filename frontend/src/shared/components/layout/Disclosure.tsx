import { ChevronDown } from 'lucide-react'
import { useId, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import { cn } from '@/shared/utils/cn'

interface DisclosureProps {
  /** Libellé du bouton, replié comme déplié. */
  label?: string
  children: ReactNode
  defaultOpen?: boolean
}

/**
 * Bloc de détail replié par défaut.
 *
 * Une ligne de commande porte une vingtaine de champs, un colis une quinzaine.
 * Les afficher tous d'emblée noie les trois qu'on lit vraiment — code-barres,
 * quantité, statut — sous ceux qu'on consulte une fois par mois. Le reste
 * s'ouvre à la demande.
 *
 * Le contenu n'est monté qu'une fois ouvert : sur une commande de vingt lignes,
 * cela évite de construire vingt tableaux que personne ne regarde.
 */
export function Disclosure({ label, children, defaultOpen = false }: DisclosureProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(defaultOpen)
  const id = useId()

  return (
    <div className="flex flex-col gap-2">
      <Button
        type="button"
        variant="ghost"
        size="sm"
        className="w-fit gap-1 px-0 text-muted-foreground hover:text-foreground"
        aria-expanded={open}
        aria-controls={id}
        onClick={() => setOpen((current) => !current)}
      >
        <ChevronDown
          className={cn('size-4 transition-transform', open && 'rotate-180')}
          aria-hidden
        />
        {label ?? t('common.moreDetails')}
      </Button>

      {open ? <div id={id}>{children}</div> : null}
    </div>
  )
}

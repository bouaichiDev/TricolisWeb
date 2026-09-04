import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Input } from '@/shared/components/ui/input'
import { Popover, PopoverAnchor, PopoverContent } from '@/shared/components/ui/popover'

interface AutocompleteInputProps {
  value: string
  onChange: (value: string) => void
  /** Valeurs existantes correspondant à la saisie, fournies par le serveur. */
  suggestions: string[]
  isLoading?: boolean
  label: string
  className?: string
}

/**
 * Une saisie complétée par ce qui existe vraiment.
 *
 * **Les suggestions viennent du serveur, jamais de la page affichée.** C'est
 * toute leur utilité : un numéro absent des vingt-cinq lignes visibles peut
 * très bien exister trois pages plus loin, et une liste tirée du tableau
 * laisserait croire le contraire.
 *
 * Le champ reste **libre** : les suggestions accélèrent la saisie, elles ne
 * l'enferment pas. Un facturier qui tape trois chiffres communs à dix
 * commandes doit pouvoir filtrer dessus sans en choisir une.
 *
 * Rendu dans un `Popover`, donc en portail : à l'intérieur d'un tableau qui
 * défile horizontalement, une liste positionnée en absolu serait rognée.
 */
export function AutocompleteInput({
  value,
  onChange,
  suggestions,
  isLoading = false,
  label,
  className,
}: AutocompleteInputProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  const visible = suggestions.filter((suggestion) => suggestion !== value)

  return (
    <Popover open={open && (visible.length > 0 || isLoading)} onOpenChange={setOpen}>
      <PopoverAnchor asChild>
        <Input
          value={value}
          onChange={(event) => {
            onChange(event.target.value)
            setOpen(true)
          }}
          onFocus={() => setOpen(true)}
          onKeyDown={(event) => event.key === 'Escape' && setOpen(false)}
          aria-label={label}
          autoComplete="off"
          className={className}
        />
      </PopoverAnchor>

      <PopoverContent
        align="start"
        className="w-56 p-1"
        // Le focus reste dans le champ : le rendre a la liste couperait la
        // saisie en cours, alors qu'on veut continuer a taper.
        onOpenAutoFocus={(event) => event.preventDefault()}
      >
        {isLoading && visible.length === 0 ? (
          <p className="px-2 py-1.5 text-sm text-muted-foreground">{t('common.loading')}</p>
        ) : (
          <ul>
            {visible.map((suggestion) => (
              <li key={suggestion}>
                <button
                  type="button"
                  className="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent"
                  onClick={() => {
                    onChange(suggestion)
                    setOpen(false)
                  }}
                >
                  {suggestion}
                </button>
              </li>
            ))}
          </ul>
        )}
      </PopoverContent>
    </Popover>
  )
}

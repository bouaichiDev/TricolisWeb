import { X } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Input } from '@/shared/components/ui/input'
import { Popover, PopoverAnchor, PopoverContent } from '@/shared/components/ui/popover'

interface MultiAutocompleteInputProps {
  values: string[]
  onChange: (values: string[]) => void
  /** Ce que la saisie en cours donne à voir, cherché par le serveur. */
  term: string
  onTermChange: (term: string) => void
  suggestions: string[]
  isLoading?: boolean
  label: string
  className?: string
}

/**
 * Une saisie qui retient plusieurs valeurs.
 *
 * **Elles se cumulent en « ou ».** Retenir les livraisons *et* les chargements
 * est une seule question, et c'est ce que le serveur applique ; les additionner
 * en « et » ne rendrait jamais rien.
 *
 * Chaque valeur devient un jeton qu'on retire d'un clic. La saisie en cours
 * n'est pas un filtre : elle cherche des suggestions, et se transforme en jeton
 * par `Entrée` ou en choisissant une proposition. Filtrer sur un texte à demi
 * tapé pendant qu'on en compose une liste rendrait la table instable sous les
 * doigts.
 *
 * Le champ reste **libre** : une valeur qu'aucune suggestion ne propose peut
 * être validée telle quelle, `Entrée` suffisant.
 */
export function MultiAutocompleteInput({
  values,
  onChange,
  term,
  onTermChange,
  suggestions,
  isLoading = false,
  label,
  className,
}: MultiAutocompleteInputProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  const visible = suggestions.filter((suggestion) => !values.includes(suggestion))

  const add = (value: string) => {
    const clean = value.trim()
    if (clean === '' || values.includes(clean)) return

    onChange([...values, clean])
    onTermChange('')
    setOpen(false)
  }

  return (
    <div className="flex flex-col gap-1">
      <Popover open={open && (visible.length > 0 || isLoading)} onOpenChange={setOpen}>
        <PopoverAnchor asChild>
          <Input
            value={term}
            onChange={(event) => {
              onTermChange(event.target.value)
              setOpen(true)
            }}
            onFocus={() => setOpen(true)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                event.preventDefault()
                add(term)
              }

              if (event.key === 'Escape') setOpen(false)

              // Retour arriere sur un champ vide : on retire le dernier jeton,
              // plutot que de laisser l'utilisateur viser une croix.
              if (event.key === 'Backspace' && term === '' && values.length > 0) {
                onChange(values.slice(0, -1))
              }
            }}
            aria-label={label}
            autoComplete="off"
            className={className}
          />
        </PopoverAnchor>

        <PopoverContent
          align="start"
          className="w-56 p-1"
          // Le focus reste dans le champ : le rendre a la liste couperait la
          // saisie, alors qu'on veut enchainer les valeurs.
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
                    onClick={() => add(suggestion)}
                  >
                    {suggestion}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </PopoverContent>
      </Popover>

      {values.length > 0 ? (
        <ul className="flex flex-wrap gap-1">
          {values.map((value) => (
            <li key={value}>
              <button
                type="button"
                onClick={() => onChange(values.filter((kept) => kept !== value))}
                className="flex items-center gap-1 rounded-full border bg-muted px-2 py-0.5 text-xs hover:bg-accent"
                aria-label={t('common.removeFilter', { value })}
              >
                {value}
                <X className="size-3" aria-hidden />
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  )
}

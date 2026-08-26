import { Search, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Input } from '@/shared/components/ui/input'

interface SearchInputProps {
  value: string
  onChange: (value: string) => void
  placeholder?: string
  /** Delai avant de propager la saisie, en millisecondes. */
  delay?: number
  /**
   * Nom accessible, quand l'ecran en porte plusieurs.
   *
   * Deux champs nommes « Rechercher » cote a cote ne se distinguent ni au
   * lecteur d'ecran ni au clavier : celui qui accompagne un panneau doit dire
   * ce qu'il cherche.
   */
  label?: string
}

/**
 * Champ de recherche a propagation differee.
 *
 * Sans ce delai, chaque frappe declencherait une requete : taper « atlas »
 * en produirait cinq, dont quatre inutiles, et la derniere pourrait revenir
 * avant l'avant-derniere.
 */
export function SearchInput({
  value,
  onChange,
  placeholder,
  delay = 350,
  label,
}: SearchInputProps) {
  const { t } = useTranslation()
  const [draft, setDraft] = useState(value)

  // La valeur peut changer hors du champ — reinitialisation des filtres.
  useEffect(() => setDraft(value), [value])

  useEffect(() => {
    if (draft === value) return

    const timer = setTimeout(() => onChange(draft), delay)
    return () => clearTimeout(timer)
  }, [draft, value, delay, onChange])

  return (
    <div className="relative w-full sm:max-w-xs">
      <Search
        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
        aria-hidden
      />
      <Input
        value={draft}
        onChange={(event) => setDraft(event.target.value)}
        placeholder={placeholder ?? t('common.searchPlaceholder')}
        className="pl-9 pr-9"
        aria-label={label ?? t('common.search')}
      />
      {draft.length > 0 ? (
        <button
          type="button"
          onClick={() => setDraft('')}
          className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition-colors hover:text-foreground"
          aria-label={t('common.reset')}
        >
          <X className="size-3.5" aria-hidden />
        </button>
      ) : null}
    </div>
  )
}

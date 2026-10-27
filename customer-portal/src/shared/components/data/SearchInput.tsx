import { Search, X } from 'lucide-react'
import { useEffect, useState } from 'react'

import { Input } from '@/shared/components/ui/input'

interface SearchInputProps {
  value: string
  onChange: (value: string) => void
  placeholder?: string
  /** Délai avant d'envoyer la saisie : une requête par mot, pas par lettre. */
  delay?: number
  label?: string
}

/** Champ de recherche du back-office : loupe, effacement, saisie différée. */
export function SearchInput({ value, onChange, placeholder = 'Rechercher…', delay = 350, label = 'Rechercher' }: SearchInputProps) {
  const [draft, setDraft] = useState(value)
  const [lastValue, setLastValue] = useState(value)

  // La valeur peut changer hors du champ (réinitialisation des filtres).
  if (value !== lastValue) {
    setLastValue(value)
    setDraft(value)
  }

  useEffect(() => {
    if (draft === value) return

    const timer = setTimeout(() => onChange(draft), delay)

    return () => clearTimeout(timer)
  }, [draft, value, delay, onChange])

  return (
    <div className="relative w-full sm:max-w-xs">
      <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" aria-hidden />
      <Input value={draft} onChange={(event) => setDraft(event.target.value)} placeholder={placeholder} className="pr-9 pl-9" aria-label={label} />
      {draft.length > 0 ? (
        <button
          type="button"
          onClick={() => setDraft('')}
          className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 -translate-y-1/2 rounded p-1 transition-colors"
          aria-label="Effacer la recherche"
        >
          <X className="size-3.5" aria-hidden />
        </button>
      ) : null}
    </div>
  )
}

import { Plus, X } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

interface AllowedIpEditorProps {
  value: string[]
  onChange: (value: string[]) => void
  error?: string
}

/**
 * Adresses et plages autorisées à porter cette clé.
 *
 * Un éditeur de liste plutôt qu'un champ JSON : le backend valide chaque entrée
 * par la règle `IpOrCidr`, donc la structure est **connue** — une liste d'IP ou
 * de CIDR. Le §26 réserve l'éditeur JSON au cas contraire.
 *
 * **Une liste vide n'est pas une restriction vide.** Sans aucune entrée, le
 * champ part en `null` et la clé fonctionne depuis n'importe où ; c'est un choix
 * légitime, mais qui doit se lire. L'écran le dit plutôt que de laisser croire
 * que le champ est simplement à remplir.
 *
 * La validation fine reste au serveur : reconnaître un CIDR IPv6 correctement
 * demande plus qu'une expression régulière, et une règle approximative ici
 * refuserait des adresses que le backend accepte.
 */
export function AllowedIpEditor({ value, onChange, error }: AllowedIpEditorProps) {
  const { t } = useTranslation()
  const [draft, setDraft] = useState('')

  const add = () => {
    const entry = draft.trim()
    if (entry === '' || value.includes(entry)) {
      setDraft('')

      return
    }

    onChange([...value, entry])
    setDraft('')
  }

  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor="allowed-ip-draft">{t('integrations.api.allowedIps')}</Label>

      <div className="flex gap-2">
        <Input
          id="allowed-ip-draft"
          value={draft}
          placeholder={t('integrations.api.allowedIpsPlaceholder')}
          onChange={(event) => setDraft(event.target.value)}
          onKeyDown={(event) => {
            if (event.key !== 'Enter') return
            // Sans cela, la touche Entrée soumettrait le formulaire entier.
            event.preventDefault()
            add()
          }}
        />
        <Button type="button" variant="outline" onClick={add} disabled={draft.trim() === ''}>
          <Plus className="size-4" aria-hidden />
          {t('common.add')}
        </Button>
      </div>

      {value.length > 0 ? (
        <ul className="flex flex-wrap gap-1.5">
          {value.map((entry) => (
            <li key={entry}>
              <Badge variant="outline" className="gap-1 font-mono">
                {entry}
                <button
                  type="button"
                  onClick={() => onChange(value.filter((item) => item !== entry))}
                  aria-label={t('common.remove')}
                  className="rounded-sm hover:text-destructive"
                >
                  <X className="size-3" aria-hidden />
                </button>
              </Badge>
            </li>
          ))}
        </ul>
      ) : null}

      {error ? (
        <p className="text-sm text-destructive">{t(error, { defaultValue: error })}</p>
      ) : (
        <p className="text-xs text-muted-foreground">
          {value.length === 0
            ? t('integrations.api.allowedIpsEmpty')
            : t('integrations.api.allowedIpsHint')}
        </p>
      )}
    </div>
  )
}

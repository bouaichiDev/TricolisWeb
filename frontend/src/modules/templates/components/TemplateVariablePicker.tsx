import { Plus, X } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

import { INVOICE_LINES_PATH } from '../utils/invoiceVariables'

interface TemplateVariablePickerProps {
  variables: string[]
  onChange: (variables: string[]) => void
  /**
   * Chemins que le contexte de rendu fournira réellement.
   *
   * Vide pour un message : `availableVariables` y est libre, aucun contexte
   * canonique n'existe, et le §23 interdit d'inventer une liste de référence.
   * Renseigné pour une facture : le contexte est connu, et les proposer évite
   * de déclarer un chemin que le serveur ne saura pas résoudre.
   */
  suggestions?: string[]
}

/**
 * Variables que le modèle déclare savoir recevoir.
 *
 * Le nom est stocké nu (`orderNumber`, `invoice.total`) ; les accolades
 * appartiennent au corps.
 *
 * Un chemin de **liste** — `invoice.lines` — se signale à part : il ne se
 * remplace pas, il se parcourt par une section. L'écrire entre accolades
 * simples donnerait un rendu en échec, et le badge le dit avant.
 */
export function TemplateVariablePicker({
  variables,
  onChange,
  suggestions = [],
}: TemplateVariablePickerProps) {
  const { t } = useTranslation()
  const [draft, setDraft] = useState('')

  const add = (name: string) => {
    const cleaned = name.trim().replace(/^\{+#?|\}+$/g, '')

    if (cleaned === '' || variables.includes(cleaned)) return

    onChange([...variables, cleaned])
    setDraft('')
  }

  const remaining = suggestions.filter((path) => !variables.includes(path))

  return (
    <div className="flex flex-col gap-2">
      <div>
        <Label htmlFor="template-variable">{t('templates.fields.availableVariables')}</Label>
        <p className="text-xs text-muted-foreground">{t('templates.variablesHint')}</p>
      </div>

      {variables.length > 0 ? (
        <ul className="flex flex-wrap gap-1.5">
          {variables.map((name) => (
            <li key={name}>
              <Badge variant="secondary" className="gap-1 font-mono">
                {name === INVOICE_LINES_PATH ? `{{#${name}}}` : `{{${name}}}`}
                <button
                  type="button"
                  onClick={() => onChange(variables.filter((item) => item !== name))}
                  aria-label={t('templates.removeVariable', { name })}
                >
                  <X className="size-3" aria-hidden />
                </button>
              </Badge>
            </li>
          ))}
        </ul>
      ) : null}

      <div className="flex gap-2">
        <Input
          id="template-variable"
          className="max-w-xs"
          value={draft}
          onChange={(event) => setDraft(event.target.value)}
          onKeyDown={(event) => {
            if (event.key !== 'Enter') return
            // Sans cela, Entree soumettrait le formulaire au lieu d'ajouter.
            event.preventDefault()
            add(draft)
          }}
          placeholder="orderNumber"
        />
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => add(draft)}
          disabled={draft.trim() === ''}
        >
          <Plus className="size-4" aria-hidden />
          {t('common.add')}
        </Button>
      </div>

      {remaining.length > 0 ? (
        <div className="flex flex-col gap-1.5">
          <p className="text-xs text-muted-foreground">{t('templates.suggestionsHint')}</p>
          <ul className="flex flex-wrap gap-1.5">
            {remaining.map((path) => (
              <li key={path}>
                <button
                  type="button"
                  className="rounded border border-dashed px-2 py-0.5 font-mono text-xs text-muted-foreground hover:border-solid hover:text-foreground"
                  onClick={() => add(path)}
                >
                  {path}
                </button>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  )
}

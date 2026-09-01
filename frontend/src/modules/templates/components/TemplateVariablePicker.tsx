import { Plus, X } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

import { INVOICE_LINES_PATH } from '../utils/invoiceVariables'
import { hasVariableLabel, variableLabel } from '../utils/variableLabel'

interface TemplateVariablePickerProps {
  variables: string[]
  onChange: (variables: string[]) => void
  /**
   * Noms que le contexte de rendu fournira réellement.
   *
   * Les proposer évite de déclarer un nom que le serveur ne saura pas résoudre
   * — l'erreur ne se voit sinon qu'à l'envoi, quand il est trop tard.
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
 *
 * Chaque nom proposé porte son **libellé** : `order_number` ne dit rien à qui
 * remplit le formulaire, « N° commande » si. Le nom technique reste visible —
 * c'est lui qu'on écrit dans le corps.
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
              <Badge variant="secondary" className="gap-1.5" title={variableLabel(t, name)}>
                <span className="font-mono">
                  {name === INVOICE_LINES_PATH ? `{{#${name}}}` : `{{${name}}}`}
                </span>
                {hasVariableLabel(t, name) ? (
                  <span className="text-muted-foreground">{variableLabel(t, name)}</span>
                ) : null}
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
                  className="flex items-center gap-1.5 rounded border border-dashed px-2 py-0.5 text-xs text-muted-foreground hover:border-solid hover:text-foreground"
                  onClick={() => add(path)}
                  title={path}
                >
                  <span>{variableLabel(t, path)}</span>
                  {hasVariableLabel(t, path) ? (
                    <span className="font-mono opacity-70">{path}</span>
                  ) : null}
                </button>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  )
}

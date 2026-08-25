import { Plus, X } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

interface TemplateVariablePickerProps {
  variables: string[]
  onChange: (variables: string[]) => void
}

/**
 * Variables que le modèle déclare savoir recevoir.
 *
 * `availableVariables` est un tableau libre côté serveur : aucune liste de
 * référence n'existe, et le §23 interdit d'en inventer une. Elles se saisissent
 * donc, et ce qui est déclaré ici est ce que le modèle annonce — pas ce que le
 * serveur garantit de substituer.
 *
 * Le nom est stocké nu (`orderNumber`) ; les accolades appartiennent au corps du
 * message.
 */
export function TemplateVariablePicker({ variables, onChange }: TemplateVariablePickerProps) {
  const { t } = useTranslation()
  const [draft, setDraft] = useState('')

  const add = () => {
    const name = draft.trim().replace(/^\{+|\}+$/g, '')

    if (name === '' || variables.includes(name)) return

    onChange([...variables, name])
    setDraft('')
  }

  return (
    <div className="flex flex-col gap-2">
      <div>
        <Label htmlFor="template-variable">
          {t('communicationTemplates.fields.availableVariables')}
        </Label>
        <p className="text-xs text-muted-foreground">
          {t('communicationTemplates.variablesHint')}
        </p>
      </div>

      {variables.length > 0 ? (
        <ul className="flex flex-wrap gap-1.5">
          {variables.map((name) => (
            <li key={name}>
              <Badge variant="secondary" className="gap-1 font-mono">
                {`{{${name}}}`}
                <button
                  type="button"
                  onClick={() => onChange(variables.filter((item) => item !== name))}
                  aria-label={t('communicationTemplates.removeVariable', { name })}
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
            add()
          }}
          placeholder="orderNumber"
        />
        <Button type="button" variant="outline" size="sm" onClick={add} disabled={draft.trim() === ''}>
          <Plus className="size-4" aria-hidden />
          {t('common.add')}
        </Button>
      </div>
    </div>
  )
}

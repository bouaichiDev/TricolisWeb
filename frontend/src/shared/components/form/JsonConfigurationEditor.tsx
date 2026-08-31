import { Check, RotateCcw, WandSparkles } from 'lucide-react'
import { useId, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import { Label } from '@/shared/components/ui/label'
import { Textarea } from '@/shared/components/ui/textarea'

interface JsonConfigurationEditorProps {
  label: string
  /** Texte JSON brut, tenu par l'appelant. */
  value: string
  onChange: (value: string) => void
  description?: string
  error?: string
  rows?: number
  /** Valeur de départ, pour le bouton de réinitialisation. */
  initialValue?: string
}

/**
 * Éditeur de configuration JSON.
 *
 * Sert partout où le serveur accepte un objet **dont il ne fixe pas la
 * structure** : `mapping`, `validationRules`, `settings`. Les Form Requests les
 * valident comme des tableaux bornés, sans schéma — inventer un formulaire à
 * champs supposerait une forme que le backend ne connaît pas, et qui casserait
 * au premier client dont le mapping diffère.
 *
 * **Rien n'est exécuté.** Le contenu est analysé par `JSON.parse` pour vérifier
 * qu'il est syntaxiquement valide, puis renvoyé tel quel. Aucun `eval`, aucune
 * fonction dynamique, aucun nom de classe interprété : le §10 et le §41
 * l'interdisent, et une configuration n'a pas à être du code.
 *
 * L'erreur affichée est celle de `JSON.parse`, qui indique la position fautive.
 * Un « JSON invalide » sans position obligerait à relire cent lignes.
 */
export function JsonConfigurationEditor({
  label,
  value,
  onChange,
  description,
  error,
  rows = 8,
  initialValue,
}: JsonConfigurationEditorProps) {
  const { t } = useTranslation()
  const id = useId()
  const [syntaxError, setSyntaxError] = useState<string | null>(null)

  const parsed = (text: string): unknown => JSON.parse(text)

  const format = () => {
    if (value.trim() === '') return

    try {
      onChange(JSON.stringify(parsed(value), null, 2))
      setSyntaxError(null)
    } catch (failure) {
      setSyntaxError(failure instanceof Error ? failure.message : t('json.invalid'))
    }
  }

  const check = (next: string) => {
    onChange(next)

    // Un champ vide est valide : `mapping` et `settings` sont nullables. Ce
    // n'est pas une erreur de syntaxe, c'est l'absence de configuration.
    if (next.trim() === '') {
      setSyntaxError(null)

      return
    }

    try {
      parsed(next)
      setSyntaxError(null)
    } catch (failure) {
      setSyntaxError(failure instanceof Error ? failure.message : t('json.invalid'))
    }
  }

  const message = error ?? syntaxError
  const valid = message === null && value.trim() !== ''

  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-center justify-between gap-2">
        <Label htmlFor={id}>{label}</Label>

        <div className="flex gap-1">
          <Button type="button" variant="ghost" size="sm" onClick={format}>
            <WandSparkles className="size-3.5" aria-hidden />
            {t('json.format')}
          </Button>

          {initialValue === undefined ? null : (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => check(initialValue)}
            >
              <RotateCcw className="size-3.5" aria-hidden />
              {t('json.reset')}
            </Button>
          )}
        </div>
      </div>

      <Textarea
        id={id}
        value={value}
        rows={rows}
        spellCheck={false}
        className="font-mono text-xs"
        aria-invalid={message !== null}
        onChange={(event) => check(event.target.value)}
      />

      {message !== null ? (
        <p className="text-sm text-destructive">{t(message, { defaultValue: message })}</p>
      ) : valid ? (
        <p className="flex items-center gap-1.5 text-xs text-success">
          <Check className="size-3.5" aria-hidden />
          {t('json.valid')}
        </p>
      ) : description ? (
        <p className="text-xs text-muted-foreground">{description}</p>
      ) : null}
    </div>
  )
}

/**
 * Texte JSON prêt pour l'écran, à partir de ce que le serveur a renvoyé.
 *
 * Un objet absent devient une chaîne vide et non `"null"` : le champ doit
 * paraître vide, pas contenir le mot `null`.
 */
export function toJsonText(value: unknown): string {
  if (value === null || value === undefined) return ''

  return JSON.stringify(value, null, 2)
}

/**
 * Objet à envoyer, à partir du texte saisi.
 *
 * Un champ vide part en `null` — le serveur l'accepte, `mapping` et `settings`
 * étant nullables. Un texte invalide remonte `undefined` : l'appelant refuse
 * alors la soumission plutôt que d'envoyer une chaîne que l'API rejetterait.
 */
export function fromJsonText(text: string): Record<string, unknown> | null | undefined {
  if (text.trim() === '') return null

  try {
    const parsed: unknown = JSON.parse(text)

    return typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)
      ? (parsed as Record<string, unknown>)
      : undefined
  } catch {
    return undefined
  }
}

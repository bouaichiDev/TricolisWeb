import { Check, ChevronDown, Copy } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import { IMPORT_TARGET_GROUPS } from '../utils/importTargetFields'

/**
 * Les champs que Tricolis accepte, à côté de l'éditeur de correspondance.
 *
 * Sans cette référence, l'écran demande d'écrire un JSON sans dire quelles clés
 * sont valides — c'est exactement le reproche qu'on peut faire à un éditeur
 * libre. Le §12 l'exige d'ailleurs : éditeur contrôlé **et documentation**.
 *
 * Ce qui est documenté, c'est le **côté gauche** du mapping : la destination,
 * relevée sur `StoreOrderRequest`. Le côté droit décrit le fichier du client et
 * n'appartient qu'à lui ; aucune syntaxe n'est imposée, parce que le backend
 * n'en définit aucune et que le §11 interdit d'inventer un langage.
 *
 * Replié par défaut : on vient d'abord saisir, la référence se consulte quand
 * la question se pose.
 */
export function ImportTargetFieldsReference() {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [copied, setCopied] = useState<string | null>(null)

  const copy = (path: string) => {
    void navigator.clipboard.writeText(path).then(() => {
      setCopied(path)
      setTimeout(() => setCopied(null), 2000)
    })
  }

  return (
    <div className="rounded-lg border bg-muted/30">
      <button
        type="button"
        onClick={() => setOpen((current) => !current)}
        aria-expanded={open}
        className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
      >
        <span className="min-w-0">
          <span className="text-sm font-medium">{t('integrations.imports.reference.title')}</span>
          <span className="mt-0.5 block text-xs text-muted-foreground">
            {t('integrations.imports.reference.subtitle')}
          </span>
        </span>

        <ChevronDown
          className={`size-4 shrink-0 transition-transform ${open ? 'rotate-180' : ''}`}
          aria-hidden
        />
      </button>

      {open ? (
        <div className="flex flex-col gap-5 border-t px-4 py-4">
          <p className="text-xs text-muted-foreground">
            {t('integrations.imports.reference.hint')}
          </p>

          {IMPORT_TARGET_GROUPS.map((group) => (
            <div key={group.key}>
              <p className="mb-2 text-xs font-medium uppercase text-muted-foreground">
                {t(`integrations.imports.reference.groups.${group.key}`)}
              </p>

              <ul className="grid gap-1 sm:grid-cols-2">
                {group.fields.map((field) => (
                  <li key={field.path} className="flex items-center gap-2">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="h-auto shrink-0 px-1.5 py-0.5 font-mono text-xs"
                      onClick={() => copy(field.path)}
                      // Le libellé porte le nom du champ, pas seulement
                      // « Copier » : un lecteur d'écran annoncerait sinon
                      // trente boutons identiques.
                      aria-label={t('integrations.imports.reference.copyField', {
                        field: field.path,
                      })}
                    >
                      {copied === field.path ? (
                        <Check className="size-3 text-success" aria-hidden />
                      ) : (
                        <Copy className="size-3 opacity-40" aria-hidden />
                      )}
                      {field.path}
                    </Button>

                    {field.ruleKey === 'optional' ? null : (
                      <Badge variant="outline" className="shrink-0 text-[10px]">
                        {t(`integrations.imports.reference.rules.${field.ruleKey}`)}
                      </Badge>
                    )}

                    {field.constraint === undefined ? null : (
                      <span className="truncate text-[11px] text-muted-foreground">
                        {field.constraint}
                      </span>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          ))}

          {/* Le point le plus important, et le plus facile à manquer. */}
          <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs">
            {t('integrations.imports.reference.notExecuted')}
          </p>
        </div>
      ) : null}
    </div>
  )
}

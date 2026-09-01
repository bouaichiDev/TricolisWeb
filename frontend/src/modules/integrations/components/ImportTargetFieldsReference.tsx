import { ChevronDown, Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import { IMPORT_TARGETS } from '../utils/importTargetFields'

interface ImportTargetFieldsReferenceProps {
  /**
   * Ajoute le champ à la correspondance, au niveau où se trouve le curseur.
   *
   * Rend `false` quand le document n'est pas exploitable — le panneau le dit
   * alors, plutôt que de laisser le clic sans effet visible.
   */
  onInsert: (fieldPath: string) => boolean
}

/**
 * Les champs que Tricolis accepte, et de quoi les écrire.
 *
 * Sans cette référence, l'écran demande d'écrire un JSON sans dire quelles clés
 * sont valides — c'est le reproche qu'on peut faire à un éditeur libre. Le §12
 * l'exige d'ailleurs : éditeur contrôlé **et** documentation.
 *
 * Elle ne se contente pas de nommer : elle **écrit**. Un clic insère le champ
 * dans la correspondance, avec la structure imbriquée qu'il faut et seulement
 * ce qui manque à l'endroit du curseur. Coller `services[].contacts[].phone`
 * comme texte ne produirait rien de valide, et laisser construire l'imbrication
 * à la main est exactement ce qui décourage devant cet écran.
 *
 * Ce qui est documenté, c'est le **côté gauche** du mapping : la destination,
 * relevée sur `StoreOrderRequest` et `StoreClaimRequest`. Le côté droit décrit
 * le fichier du client et n'appartient qu'à lui ; aucune syntaxe n'est imposée,
 * parce que le backend n'en définit aucune et que le §11 interdit d'inventer un
 * langage.
 *
 * Commande et réclamation sont présentées **séparément** : ce sont deux
 * documents distincts, servis par deux endpoints. Les fondre laisserait croire
 * qu'un même fichier porte des colis et un type de réclamation.
 */
export function ImportTargetFieldsReference({ onInsert }: ImportTargetFieldsReferenceProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [refused, setRefused] = useState(false)

  const add = (fieldPath: string) => {
    setRefused(!onInsert(fieldPath))
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

          {refused ? (
            <p className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs">
              {t('integrations.imports.reference.refused')}
            </p>
          ) : null}

          {IMPORT_TARGETS.map((target) => (
            <section key={target.key} className="flex flex-col gap-4">
              <p className="border-b pb-1.5 text-sm font-medium">
                {t(`integrations.imports.reference.targets.${target.key}`)}
              </p>

              {target.groups.map((group) => (
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
                          className="h-auto shrink-0 gap-1 px-1.5 py-0.5 font-mono text-xs"
                          onClick={() => add(field.path)}
                          // Le libellé porte le nom du champ, pas seulement
                          // « Ajouter » : un lecteur d'écran annoncerait sinon
                          // soixante boutons identiques.
                          aria-label={t('integrations.imports.reference.addField', {
                            field: field.path,
                          })}
                        >
                          <Plus className="size-3 opacity-40" aria-hidden />
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
            </section>
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

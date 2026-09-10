import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { Skeleton } from '@/shared/components/ui/skeleton'

import { TemplateForm } from './TemplateForm'
import { TemplatePreview } from './TemplatePreview'
import { useCreateTemplate, useTemplate, useUpdateTemplate } from '../hooks/useTemplates'
import {
  defaultsFor,
  isTemplateComplete,
  toDuplicateFormValues,
  toTemplateFormValues,
  toTemplatePayload,
  type TemplateFormValues,
} from '../schemas/templateSchema'
import type { Template } from '../types/template'

interface TemplateDialogProps {
  /** `null` pour une création. */
  template: Template | null
  /**
   * Le modèle **recopié**, quand le dialogue sert à dupliquer.
   *
   * Distinct de `template` : la duplication crée, elle ne modifie pas. Les
   * confondre aurait écrasé le modèle d'origine à l'enregistrement.
   */
  duplicateOf?: Template | null
  open: boolean
  onOpenChange: (open: boolean) => void
  /**
   * Valeurs imposées à la création.
   *
   * Sert aux deux accès du menu : ouvrir « Templates de facture » puis devoir
   * choisir le type serait une question dont l'écran connaît déjà la réponse.
   */
  initial?: Partial<TemplateFormValues>
}

/**
 * Création et modification d'un modèle.
 *
 * **La modification recharge le modèle avant d'ouvrir le formulaire.** La liste
 * n'expose ni le corps, ni l'objet, ni les variables — ce sont des LONGTEXT que
 * le §37 interdit de charger par ligne. Éditer depuis la ligne de liste
 * ouvrirait donc un formulaire au corps vide, et l'enregistrer effacerait le
 * contenu du modèle sans que personne l'ait demandé.
 */
export function TemplateDialog({
  template,
  duplicateOf = null,
  open,
  onOpenChange,
  initial,
}: TemplateDialogProps) {
  const { t } = useTranslation()

  // Une duplication charge elle aussi le modele complet : la liste ne transporte
  // ni corps ni variables, et recopier une ligne de liste donnerait un duplicata
  // vide — la copie la plus inutile qui soit.
  const source = template ?? duplicateOf
  const detail = useTemplate(source?.id)

  const loading = source !== null && detail.data === undefined

  const title =
    template !== null
      ? t('templates.edit')
      : duplicateOf !== null
        ? t('templates.duplicate')
        : t('templates.create')

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] max-w-3xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>
            {duplicateOf === null ? t('templates.formHint') : t('templates.duplicateHint')}
          </DialogDescription>
        </DialogHeader>

        {loading ? (
          <Skeleton className="h-72 w-full" />
        ) : (
          <TemplateDialogBody
            template={template === null ? null : (detail.data ?? null)}
            duplicate={duplicateOf === null ? null : (detail.data ?? null)}
            initial={initial}
            onDone={() => onOpenChange(false)}
          />
        )}
      </DialogContent>
    </Dialog>
  )
}

interface TemplateDialogBodyProps {
  template: Template | null
  duplicate: Template | null
  initial?: Partial<TemplateFormValues>
  onDone: () => void
}

/**
 * Le formulaire lui-même, monté une fois le modèle connu.
 *
 * Séparé du dialogue parce que son état de départ se calcule à la construction :
 * le monter plus tôt figerait des champs vides, qu'un effet devrait ensuite
 * rattraper — et l'utilisateur verrait sa saisie écrasée à l'arrivée des
 * données.
 *
 * Le `code` n'est pas modifiable après coup. Il identifie le modèle — c'est par
 * lui qu'on le retrouve — et le renommer romprait cette référence sans
 * prévenir.
 */
function TemplateDialogBody({ template, duplicate, initial, onDone }: TemplateDialogBodyProps) {
  const { t } = useTranslation()
  const isEdit = template !== null

  const [values, setValues] = useState<TemplateFormValues>(() => {
    if (template !== null) return toTemplateFormValues(template)
    if (duplicate !== null) return toDuplicateFormValues(duplicate)

    return { ...defaultsFor(initial?.templateType), ...initial }
  })
  const [error, setError] = useState<string | null>(null)

  const create = useCreateTemplate()
  const update = useUpdateTemplate()

  const submit = async () => {
    setError(null)

    try {
      const payload = toTemplatePayload(values)

      if (isEdit) await update.mutateAsync({ id: template.id, ...payload })
      else await create.mutateAsync(payload)

      onDone()
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
    }
  }

  return (
    <>
      <FormErrorSummary message={error} />

      <TemplateForm
        values={values}
        onChange={(patch) => setValues((current) => ({ ...current, ...patch }))}
        codeEditable={!isEdit}
      />

      <TemplatePreview values={values} />

      <DialogFooter>
        <Button type="button" variant="ghost" onClick={onDone}>
          {t('common.cancel')}
        </Button>
        <Button
          type="button"
          onClick={() => void submit()}
          disabled={!isTemplateComplete(values) || create.isPending || update.isPending}
        >
          {t('common.save')}
        </Button>
      </DialogFooter>
    </>
  )
}

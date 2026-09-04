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
  INVOICE_FORM_DEFAULTS,
  TEMPLATE_FORM_DEFAULTS,
  isTemplateComplete,
  toTemplateFormValues,
  toTemplatePayload,
  type TemplateFormValues,
} from '../schemas/templateSchema'
import type { Template } from '../types/template'

interface TemplateDialogProps {
  /** `null` pour une création. */
  template: Template | null
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
export function TemplateDialog({ template, open, onOpenChange, initial }: TemplateDialogProps) {
  const { t } = useTranslation()
  const detail = useTemplate(template?.id)

  // En création, il n'y a rien à charger. En modification, le formulaire attend
  // le modèle complet plutôt que de s'ouvrir sur des champs vides.
  const loading = template !== null && detail.data === undefined

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] max-w-3xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{template === null ? t('templates.create') : t('templates.edit')}</DialogTitle>
          <DialogDescription>{t('templates.formHint')}</DialogDescription>
        </DialogHeader>

        {loading ? (
          <Skeleton className="h-72 w-full" />
        ) : (
          <TemplateDialogBody
            template={detail.data ?? null}
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
function TemplateDialogBody({ template, initial, onDone }: TemplateDialogBodyProps) {
  const { t } = useTranslation()
  const isEdit = template !== null

  const [values, setValues] = useState<TemplateFormValues>(() => {
    if (template !== null) return toTemplateFormValues(template)

    const base =
      initial?.templateType === 'invoice' ? INVOICE_FORM_DEFAULTS : TEMPLATE_FORM_DEFAULTS

    return { ...base, ...initial }
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

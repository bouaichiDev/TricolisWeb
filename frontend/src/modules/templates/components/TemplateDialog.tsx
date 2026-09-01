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

import { TemplateForm } from './TemplateForm'
import { TemplatePreview } from './TemplatePreview'
import { useCreateTemplate, useUpdateTemplate } from '../hooks/useTemplates'
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
 * Le formulaire et son aperçu vivent côte à côte : écrire un modèle sans voir
 * ce qu'il donne oblige à enregistrer pour vérifier, puis à revenir.
 *
 * Le `code` n'est pas modifiable après coup. Il identifie le modèle — c'est par
 * lui qu'on le retrouve — et le renommer romprait cette référence sans
 * prévenir.
 */
export function TemplateDialog({ template, open, onOpenChange, initial }: TemplateDialogProps) {
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

      onOpenChange(false)
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] max-w-3xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{isEdit ? t('templates.edit') : t('templates.create')}</DialogTitle>
          <DialogDescription>{t('templates.formHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        <TemplateForm
          values={values}
          onChange={(patch) => setValues((current) => ({ ...current, ...patch }))}
          codeEditable={!isEdit}
        />

        <TemplatePreview values={values} />

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
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
      </DialogContent>
    </Dialog>
  )
}

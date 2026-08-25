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

import { CommunicationTemplateForm } from './CommunicationTemplateForm'
import { CommunicationTemplatePreview } from './CommunicationTemplatePreview'
import {
  useCreateCommunicationTemplate,
  useUpdateCommunicationTemplate,
} from '../hooks/useCommunicationTemplates'
import {
  isTemplateComplete,
  TEMPLATE_FORM_DEFAULTS,
  toTemplateFormValues,
  toTemplatePayload,
  type TemplateFormValues,
} from '../schemas/templateForm'
import type { CommunicationTemplate } from '../types/communication'

interface CommunicationTemplateDialogProps {
  /** `null` pour une création. */
  template: CommunicationTemplate | null
  open: boolean
  onOpenChange: (open: boolean) => void
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
export function CommunicationTemplateDialog({
  template,
  open,
  onOpenChange,
}: CommunicationTemplateDialogProps) {
  const { t } = useTranslation()
  const isEdit = template !== null

  const [values, setValues] = useState<TemplateFormValues>(() =>
    template === null ? TEMPLATE_FORM_DEFAULTS : toTemplateFormValues(template),
  )
  const [error, setError] = useState<string | null>(null)

  const create = useCreateCommunicationTemplate()
  const update = useUpdateCommunicationTemplate()

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
          <DialogTitle>
            {isEdit ? t('communicationTemplates.edit') : t('communicationTemplates.create')}
          </DialogTitle>
          <DialogDescription>{t('communicationTemplates.formHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        <CommunicationTemplateForm
          values={values}
          onChange={(patch) => setValues((current) => ({ ...current, ...patch }))}
          codeEditable={!isEdit}
        />

        <CommunicationTemplatePreview values={values} />

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            onClick={() => void submit()}
            disabled={
              !isTemplateComplete(values) || create.isPending || update.isPending
            }
          >
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

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

import { CommunicationRuleForm } from './CommunicationRuleForm'
import { useCreateCommunicationRule, useUpdateCommunicationRule } from '../hooks/useCommunicationRules'
import {
  RULE_FORM_DEFAULTS,
  isRuleComplete,
  toRuleFormValues,
  toRulePayload,
  type RuleFormValues,
} from '../schemas/ruleSchema'
import type { CommunicationRule } from '../types/communicationRule'

interface RuleDialogProps {
  /** `null` pour une création. */
  rule: CommunicationRule | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Création et modification d'une règle.
 *
 * Modifier une règle ne touche **jamais** les messages qu'elle a déjà produits :
 * ceux-ci portent leur propre instantané. Les prochains emploieront la nouvelle
 * version.
 */
export function CommunicationRuleDialog({ rule, open, onOpenChange }: RuleDialogProps) {
  const { t } = useTranslation()
  const isEdit = rule !== null

  const [values, setValues] = useState<RuleFormValues>(() =>
    rule === null ? RULE_FORM_DEFAULTS : toRuleFormValues(rule),
  )
  const [error, setError] = useState<string | null>(null)

  const create = useCreateCommunicationRule()
  const update = useUpdateCommunicationRule()

  const submit = async () => {
    setError(null)

    try {
      const payload = toRulePayload(values)

      if (isEdit) await update.mutateAsync({ id: rule.id, ...payload })
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
            {isEdit ? t('communicationRules.edit') : t('communicationRules.create')}
          </DialogTitle>
          <DialogDescription>{t('communicationRules.formHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        <CommunicationRuleForm
          values={values}
          onChange={(patch) => setValues((current) => ({ ...current, ...patch }))}
        />

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            onClick={() => void submit()}
            disabled={!isRuleComplete(values) || create.isPending || update.isPending}
          >
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

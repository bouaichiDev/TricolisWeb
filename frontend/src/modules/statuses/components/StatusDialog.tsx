import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledCheckbox } from '@/shared/components/form/ControlledCheckbox'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

import { useCreateStatus, useStatusSources, useUpdateStatus } from '../hooks/useStatuses'
import type { Status, StatusPayload } from '../types/status'
import { STATUS_FIELDS, statusFormValues } from './statusForm'

/**
 * Les quatre comportements reglables d'un statut.
 *
 * Les deux derniers gouvernaient le code — `allowsContentChanges()` et
 * `requiresReason()` sur l'enumeration — et sont passes au referentiel avec les
 * transitions : laisser une moitie des regles dans le code aurait reconduit
 * exactement l'incoherence qu'on corrige.
 */
const FLAGS = [
  { name: 'active', hintKey: 'statuses.activeHint' },
  { name: 'isToSend', hintKey: 'statuses.isToSendHint' },
  { name: 'allowsContentChanges', hintKey: 'statuses.allowsContentChangesHint' },
  { name: 'requiresReason', hintKey: 'statuses.requiresReasonHint' },
] as const

type FlagName = (typeof FLAGS)[number]['name']

const DEFAULT_FLAGS: Record<FlagName, boolean> = {
  active: true,
  isToSend: false,
  allowsContentChanges: false,
  requiresReason: false,
}

interface StatusDialogProps {
  status: Status | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Ajout ou correction d'un statut du référentiel.
 *
 * **L'entité n'est choisie qu'à la création.** Déplacer un statut d'un domaine
 * à l'autre emmènerait en silence tous les enregistrements qui portent déjà son
 * code ; le serveur refuse d'ailleurs de la modifier.
 *
 * Le code est la valeur stockée dans les colonnes `status` du domaine : le
 * changer sur un statut déjà utilisé laisserait ces enregistrements sans
 * libellé. Le champ le rappelle.
 */
export function StatusDialog({ status, open, onOpenChange }: StatusDialogProps) {
  const { t } = useTranslation()
  const sources = useStatusSources()
  const create = useCreateStatus()
  const update = useUpdateStatus()

  const [values, setValues] = useState<Record<string, string>>({})
  const [flags, setFlags] = useState<Partial<Record<FlagName, boolean>>>({})
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  const current = { ...statusFormValues(status), ...values }

  const flag = (name: FlagName): boolean => flags[name] ?? status?.[name] ?? DEFAULT_FLAGS[name]

  const patch = (field: string, value: string) =>
    setValues((previous) => ({ ...previous, [field]: value }))

  const close = () => {
    setValues({})
    setFlags({})
    setErrors({})
    setFormError(null)
    onOpenChange(false)
  }

  const onError = (cause: unknown) => {
    if (cause instanceof ApiError && cause.isValidation) {
      setErrors(
        Object.fromEntries(
          Object.entries(cause.errors).map(([field, messages]) => [field, messages[0]]),
        ),
      )
      return
    }

    setFormError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
  }

  const submit = () => {
    setErrors({})
    setFormError(null)

    const payload: StatusPayload = {
      status: Number(current.status),
      code: current.code.trim(),
      label: current.label.trim(),
      icon: current.icon.trim() === '' ? null : current.icon.trim(),
      position: current.position.trim() === '' ? null : Number(current.position),
      active: flag('active'),
      isToSend: flag('isToSend'),
      allowsContentChanges: flag('allowsContentChanges'),
      requiresReason: flag('requiresReason'),
    }

    if (status) update.mutate({ id: status.id, ...payload }, { onSuccess: close, onError })
    else create.mutate({ ...payload, source: current.source }, { onSuccess: close, onError })
  }

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? onOpenChange(true) : close())}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{status ? t('statuses.edit') : t('statuses.create')}</DialogTitle>
          <DialogDescription>{t('statuses.subtitle')}</DialogDescription>
        </DialogHeader>

        {formError !== null ? (
          <Alert variant="destructive">
            <AlertDescription>{formError}</AlertDescription>
          </Alert>
        ) : null}

        <div className="grid gap-4 sm:grid-cols-2">
          <AsyncSelect
            label={t('statuses.fields.source')}
            value={current.source}
            onChange={(value) => patch('source', value)}
            options={(sources.data ?? []).map((source) => ({
              value: source,
              label: t(`entities.${source}`, { defaultValue: source }),
              hint: source,
            }))}
            isLoading={sources.isPending}
            disabled={status !== null}
            required
            description={status !== null ? t('statuses.sourceLocked') : undefined}
            error={errors.source}
          />

          {STATUS_FIELDS.map((spec) => (
            <ControlledField
              key={spec.name}
              label={t(spec.labelKey)}
              type={spec.type}
              min={spec.type === 'number' ? '0' : undefined}
              step={spec.type === 'number' ? '1' : undefined}
              value={current[spec.name] ?? ''}
              onChange={(value) => patch(spec.name, value)}
              required={spec.required}
              description={spec.hintKey ? t(spec.hintKey) : undefined}
              error={errors[spec.name]}
            />
          ))}
        </div>

        <div className="grid gap-1 sm:grid-cols-2">
          {FLAGS.map(({ name, hintKey }) => (
            <ControlledCheckbox
              key={name}
              label={t(`statuses.fields.${name}`)}
              checked={flag(name)}
              onChange={(checked) => setFlags((previous) => ({ ...previous, [name]: checked }))}
              description={t(hintKey)}
            />
          ))}
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={close}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={submit} disabled={create.isPending || update.isPending}>
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

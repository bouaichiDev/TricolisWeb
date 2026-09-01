import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useStatusOptions } from '@/modules/statuses/hooks/useStatuses'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'
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

import { useCreateExportJob, useExportConfigurationList } from '../hooks/useExports'

/**
 * Le seul type d'entité réellement exportable.
 *
 * `ExportDispatcher` ne connaît que la facture : `invoiceOf()` refuse tout
 * autre `entity_type`, et les cinq formatteurs sont des formatteurs de facture.
 * Le §60 l'exige — n'implémenter que les types dont le mapping métier existe,
 * ne pas produire de faux contenu — et le §62 demande précisément cette liste
 * blanche.
 */
const EXPORTABLE_ENTITY_TYPES = ['invoice'] as const

const EXPORT_JOB_SOURCE = 'export_job'

interface GenerateExportDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Déclencher un envoi à la main.
 *
 * L'usage courant reste **automatique** : clôturer une facture crée l'envoi
 * sans que personne le demande. Ce dialogue sert aux cas où il faut renvoyer
 * vers une autre destination, ou rattraper une configuration ajoutée après
 * coup.
 *
 * **La règle de la Phase 6 tient.** Une facture non clôturée est refusée par
 * `ExportDispatcher`, quel que soit le chemin : ce dialogue ne la contourne pas
 * (§63). Le refus arrive à l'exécution, en file, et se lit sur l'envoi.
 *
 * React ne produit ni ne transmet rien : `ProcessExportJob` s'en charge.
 */
export function GenerateExportDialog({ open, onOpenChange }: GenerateExportDialogProps) {
  const { t } = useTranslation()
  const create = useCreateExportJob()
  const statuses = useStatusOptions(EXPORT_JOB_SOURCE)

  const configurations = useExportConfigurationList({ page: 1, perPage: 100, isActive: true })

  const [configurationId, setConfigurationId] = useState('')
  const [entityType, setEntityType] = useState<string>(EXPORTABLE_ENTITY_TYPES[0])
  const [entityId, setEntityId] = useState('')
  const [error, setError] = useState<string | null>(null)

  const reset = () => {
    setConfigurationId('')
    setEntityId('')
    setError(null)
  }

  const submit = () => {
    setError(null)

    if (configurationId === '' || entityId.trim() === '') {
      setError(t('validation.required'))

      return
    }

    create.mutate(
      {
        configurationId,
        entityType,
        entityId: entityId.trim(),
        // Le premier statut du référentiel : un envoi naît en attente, et c'est
        // l'ordre du référentiel qui le dit, pas une constante écrite ici.
        status: statuses.options[0]?.value ?? 'pending',
      },
      {
        onSuccess: () => {
          reset()
          onOpenChange(false)
        },
      },
    )
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) reset()
        onOpenChange(next)
      }}
    >
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{t('exports.jobs.generate')}</DialogTitle>
          <DialogDescription>{t('exports.jobs.generateHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        <div className="flex flex-col gap-5">
          <AsyncSelect
            label={t('exports.jobs.fields.configuration')}
            value={configurationId}
            onChange={setConfigurationId}
            options={(configurations.data?.data ?? []).map((configuration) => ({
              value: configuration.id,
              label: configuration.name,
              hint: `${configuration.transport} · ${configuration.format}`,
            }))}
            isLoading={configurations.isPending}
            required
            description={t('exports.jobs.configurationHint')}
          />

          <AsyncSelect
            label={t('exports.jobs.fields.entityType')}
            value={entityType}
            onChange={setEntityType}
            options={EXPORTABLE_ENTITY_TYPES.map((value) => ({
              value,
              label: t(`entities.${value}`, { defaultValue: value }),
            }))}
            required
            description={t('exports.jobs.entityTypeHint')}
          />

          <ControlledField
            label={t('exports.jobs.fields.entityId')}
            value={entityId}
            onChange={setEntityId}
            required
            description={t('exports.jobs.entityIdHint')}
          />
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={submit} disabled={create.isPending}>
            {t('exports.jobs.generate')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

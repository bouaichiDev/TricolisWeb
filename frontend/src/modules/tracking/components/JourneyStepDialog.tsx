import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useApiConfigurationList } from '@/modules/integrations/hooks/useApiConfigurations'
import { useStatusList, useStatusSources } from '@/modules/statuses/hooks/useStatuses'
import { ApiError } from '@/shared/api/errors'
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
import { Label } from '@/shared/components/ui/label'
import { Switch } from '@/shared/components/ui/switch'

import { useCreateTrackingDefinition, useUpdateTrackingDefinition } from '../hooks/useTracking'
import type { TrackingEventDefinition } from '../types/trackingDefinition'

interface JourneyStepDialogProps {
  /** `null` pour une création. */
  step: TrackingEventDefinition | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** Valeur désignant « aucune API », Radix refusant une option vide. */
const NO_API = 'none'

/**
 * Une étape du parcours : quel statut la déclenche, ce que le client lit.
 *
 * L'entité et le statut viennent des **référentiels existants** : les sources
 * de `statuses` pour la première, les statuts décrits pour la seconde. Saisir
 * l'un ou l'autre à la main créerait des étapes que rien ne déclencherait
 * jamais — le serveur valide d'ailleurs l'entité contre la morph map.
 *
 * L'API n'est demandée que si l'étape se suit en direct. Non nulle, elle dit
 * **laquelle** renseigne la position : un simple drapeau aurait laissé la
 * question ouverte.
 */
export function JourneyStepDialog({ step, open, onOpenChange }: JourneyStepDialogProps) {
  const { t } = useTranslation()
  const isEdit = step !== null

  const [sourceType, setSourceType] = useState(step?.sourceType ?? '')
  const [statusCode, setStatusCode] = useState(step?.statusCode ?? '')
  const [code, setCode] = useState(step?.code ?? '')
  const [title, setTitle] = useState(step?.title ?? '')
  const [description, setDescription] = useState(step?.description ?? '')
  const [position, setPosition] = useState(String(step?.position ?? 10))
  const [apiId, setApiId] = useState(step?.apiConfigurationId ?? NO_API)
  const [active, setActive] = useState(step?.active ?? true)
  const [error, setError] = useState<string | null>(null)

  const sources = useStatusSources()
  const statuses = useStatusList(
    { page: 1, perPage: 100, source: sourceType, sort: 'position', direction: 'asc' },
    open && sourceType !== '',
  )
  const apis = useApiConfigurationList({ page: 1, perPage: 100 }, open)

  const create = useCreateTrackingDefinition()
  const update = useUpdateTrackingDefinition()

  const incomplete =
    sourceType === '' || statusCode === '' || code.trim() === '' || title.trim() === ''

  const submit = async () => {
    setError(null)

    try {
      const payload = {
        sourceType,
        statusCode,
        code: code.trim(),
        title: title.trim(),
        description: description.trim() === '' ? null : description.trim(),
        position: Number(position) || 0,
        apiConfigurationId: apiId === NO_API ? null : apiId,
        active,
      }

      if (isEdit) await update.mutateAsync({ id: step.id, ...payload })
      else await create.mutateAsync(payload)

      onOpenChange(false)
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
    }
  }

  const noStatus =
    sourceType !== '' && !statuses.isPending && (statuses.data?.data ?? []).length === 0

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] max-w-lg overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{isEdit ? t('journey.edit') : t('journey.create')}</DialogTitle>
          <DialogDescription>{t('journey.formHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        <div className="flex flex-col gap-5">
          <div className="grid gap-4 sm:grid-cols-2">
            <AsyncSelect
              label={t('journey.fields.sourceType')}
              value={sourceType}
              onChange={(next) => {
                setSourceType(next)
                // Le statut retenu appartenait a l'autre entite : le garder
                // decrirait un declencheur qui n'existe pas.
                setStatusCode('')
              }}
              options={(sources.data ?? []).map((source) => ({ value: source, label: source }))}
              isLoading={sources.isPending}
              required
              description={t('journey.sourceHint')}
            />

            <AsyncSelect
              label={t('journey.fields.statusCode')}
              value={statusCode}
              onChange={setStatusCode}
              options={(statuses.data?.data ?? []).map((status) => ({
                value: status.code,
                label: status.label,
                hint: status.code,
              }))}
              isLoading={statuses.isPending}
              disabled={sourceType === ''}
              required
              description={
                sourceType === ''
                  ? t('journey.pickSourceFirst')
                  : noStatus
                    ? t('journey.noStatus')
                    : undefined
              }
            />
          </div>

          <ControlledField
            label={t('journey.fields.title')}
            value={title}
            onChange={setTitle}
            required
            description={t('journey.titleHint')}
          />

          <div className="grid gap-4 sm:grid-cols-2">
            <ControlledField
              label={t('journey.fields.code')}
              value={code}
              onChange={setCode}
              required
              description={t('journey.codeHint')}
            />
            <ControlledField
              label={t('journey.fields.position')}
              type="number"
              min="0"
              value={position}
              onChange={setPosition}
              description={t('journey.positionHint')}
            />
          </div>

          <ControlledField
            label={t('journey.fields.description')}
            value={description}
            onChange={setDescription}
            multiline
          />

          <AsyncSelect
            label={t('journey.fields.api')}
            value={apiId}
            onChange={setApiId}
            options={[
              { value: NO_API, label: t('journey.noApi') },
              ...(apis.data?.data ?? []).map((api) => ({
                value: api.id,
                label: api.name,
                hint: api.code,
              })),
            ]}
            isLoading={apis.isPending}
            description={t('journey.apiHint')}
          />

          <span className="flex items-center gap-2">
            <Switch id="journey-active" checked={active} onCheckedChange={setActive} />
            <Label htmlFor="journey-active">{t('journey.fields.active')}</Label>
          </span>
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            onClick={() => void submit()}
            disabled={incomplete || create.isPending || update.isPending}
          >
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

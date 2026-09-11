import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Button } from '@/shared/components/ui/button'

import {
  useCreateStatusPropagation,
  useUpdateStatusPropagation,
} from '../hooks/useStatusPropagations'
import { useStatusOptions } from '../hooks/useStatuses'
import type { StatusHierarchy, StatusPropagation } from '../types/status'

interface StatusPropagationFormProps {
  hierarchy: StatusHierarchy
  /** Règle en cours de correction ; `null` pour en déclarer une nouvelle. */
  rule?: StatusPropagation | null
  onDone?: () => void
}

/** Entités qui entrent dans la hiérarchie, dans l'ordre où on les lit. */
function entitiesOf(hierarchy: StatusHierarchy): string[] {
  const all = new Set<string>()

  for (const [parent, children] of Object.entries(hierarchy)) {
    all.add(parent)
    children.forEach((child) => all.add(child))
  }

  return [...all]
}

/** Entités reliées à celle-ci — ses contenants et son contenu. */
function relativesOf(hierarchy: StatusHierarchy, entity: string): string[] {
  const children = hierarchy[entity] ?? []
  const parents = Object.entries(hierarchy)
    .filter(([, value]) => value.includes(entity))
    .map(([parent]) => parent)

  return [...parents, ...children]
}

/**
 * Déclarer une règle : « quand ceci arrive, cela suit ».
 *
 * Les entités proposées à droite sont **celles qui sont reliées** à celle de
 * gauche : une règle entre une commande et un véhicule n'aurait aucun chemin à
 * suivre, et le serveur la refuserait de toute façon.
 *
 * Le mode — tous les enfants, ou un seul — n'apparaît que lorsque la règle
 * remonte vers un contenant. Vers le contenu, la question ne se pose pas : tous
 * suivent.
 */
export function StatusPropagationForm({
  hierarchy,
  rule = null,
  onDone,
}: StatusPropagationFormProps) {
  const { t } = useTranslation()
  const [fromSource, setFromSource] = useState(rule?.from?.source ?? '')
  const [fromStatusId, setFromStatusId] = useState(rule?.fromStatusId ?? '')
  const [toSource, setToSource] = useState(rule?.to?.source ?? '')
  const [toStatusId, setToStatusId] = useState(rule?.toStatusId ?? '')
  const [mode, setMode] = useState(rule?.mode ?? 'all')

  const create = useCreateStatusPropagation()
  const update = useUpdateStatusPropagation()
  const from = useStatusOptions(fromSource)
  const to = useStatusOptions(toSource)

  const entityOptions = (sources: string[]) =>
    sources.map((source) => ({
      value: source,
      label: t(`entities.${source}`, { defaultValue: source }),
      hint: source,
    }))

  const byId = (options: { statuses: { id: string; label: string; code: string }[] }) =>
    options.statuses.map((status) => ({
      value: status.id,
      label: status.label,
      hint: status.code,
    }))

  // La règle remonte : le contenant n'y passe que si ses enfants y sont.
  const climbing = (hierarchy[toSource] ?? []).includes(fromSource)

  const ready = fromStatusId !== '' && toStatusId !== ''

  const submit = () => {
    const payload = { fromStatusId, toStatusId, mode: climbing ? mode : 'all' }

    const done = () => {
      setFromStatusId('')
      setToStatusId('')
      onDone?.()
    }

    rule === null
      ? create.mutate(payload, { onSuccess: done })
      : update.mutate({ id: rule.id, ...payload }, { onSuccess: done })
  }

  return (
    <div className="flex flex-col gap-3 rounded-lg border p-3">
      <p className="text-sm font-medium">
        {t(rule === null ? 'statuses.propagations.add' : 'statuses.propagations.editing')}
      </p>

      <div className="grid gap-3 sm:grid-cols-2">
        <AsyncSelect
          label={t('statuses.propagations.whenEntity')}
          value={fromSource}
          onChange={(value) => {
            setFromSource(value)
            setFromStatusId('')
            setToSource('')
            setToStatusId('')
          }}
          options={entityOptions(entitiesOf(hierarchy))}
        />

        <AsyncSelect
          label={t('statuses.propagations.whenStatus')}
          value={fromStatusId}
          onChange={setFromStatusId}
          options={byId(from)}
          isLoading={from.isLoading}
          disabled={fromSource === ''}
          description={fromSource === '' ? t('statuses.propagations.pickEntityFirst') : undefined}
        />

        <AsyncSelect
          label={t('statuses.propagations.thenEntity')}
          value={toSource}
          onChange={(value) => {
            setToSource(value)
            setToStatusId('')
          }}
          options={entityOptions(relativesOf(hierarchy, fromSource))}
          disabled={fromSource === ''}
        />

        <AsyncSelect
          label={t('statuses.propagations.thenStatus')}
          value={toStatusId}
          onChange={setToStatusId}
          options={byId(to)}
          isLoading={to.isLoading}
          disabled={toSource === ''}
        />

        {climbing ? (
          <AsyncSelect
            label={t('statuses.propagations.mode')}
            value={mode}
            onChange={setMode}
            options={[
              { value: 'all', label: t('statuses.propagations.modeAll') },
              { value: 'any', label: t('statuses.propagations.modeAny') },
            ]}
            description={t('statuses.propagations.modeHint')}
          />
        ) : null}
      </div>

      <div className="flex gap-2">
        <Button
          type="button"
          onClick={submit}
          disabled={!ready || create.isPending || update.isPending}
        >
          <Plus className="size-4" aria-hidden />
          {t(rule === null ? 'common.add' : 'common.save')}
        </Button>

        {rule === null ? null : (
          <Button type="button" variant="ghost" onClick={onDone}>
            {t('common.cancel')}
          </Button>
        )}
      </div>
    </div>
  )
}

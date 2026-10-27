import { useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

import { useSaveImportConnection } from '../hooks/useImportConnection'
import type { ImportConnectionState, RestPullConnectionPayload, RestPullSettings } from '../types/importConnection'

type RestConnection = Extract<NonNullable<ImportConnectionState['connection']>, { kind: 'rest' }>

const AUTH_TYPES = ['none', 'bearer', 'api_key', 'basic'] as const
const PAGINATION_TYPES = ['none', 'page', 'offset', 'cursor'] as const

/**
 * La connexion d'un import en pull REST. Le secret est **en écriture seule** :
 * le champ reste vide, et n'est envoyé que s'il est rempli — le laisser vide
 * garde le secret enregistré.
 */
export function RestPullConnectionForm({ configurationId, connection }: { configurationId: string; connection: RestConnection }) {
  const { t } = useTranslation()
  const save = useSaveImportConnection(configurationId)
  const settings: RestPullSettings = connection.settings ?? {}
  const [values, setValues] = useState({
    baseUrl: connection.baseUrl ?? '',
    authType: connection.authType ?? 'bearer',
    credentials: '',
    path: settings.path ?? '',
    healthPath: settings.healthPath ?? '',
    recordsPath: settings.recordsPath ?? '',
    paginationType: settings.pagination?.type ?? 'none',
    pageParam: settings.pagination?.param ?? '',
    pageSize: String(settings.pagination?.size ?? 100),
    sinceParam: settings.since?.param ?? '',
    actorUserId: settings.actorUserId ?? '',
  })
  const set = (key: keyof typeof values) => (event: { target: { value: string } }) => setValues((current) => ({ ...current, [key]: event.target.value }))

  const submit = (event: FormEvent) => {
    event.preventDefault()
    const payload: RestPullConnectionPayload = {
      baseUrl: values.baseUrl,
      authType: values.authType as RestPullConnectionPayload['authType'],
      settings: {
        path: values.path || null,
        healthPath: values.healthPath || null,
        recordsPath: values.recordsPath || null,
        pagination: { type: values.paginationType as NonNullable<RestPullSettings['pagination']>['type'], param: values.pageParam || null, size: Number(values.pageSize) || 100 },
        since: values.sinceParam ? { param: values.sinceParam } : null,
        actorUserId: values.actorUserId || null,
      },
      ...(values.credentials !== '' ? { credentials: values.credentials } : {}),
    }
    save.mutate(payload, { onSuccess: () => setValues((current) => ({ ...current, credentials: '' })) })
  }

  const field = (key: keyof typeof values, label: string, type = 'text') => (
    <div className="grid gap-1.5">
      <Label htmlFor={`rest-${key}`}>{label}</Label>
      <Input id={`rest-${key}`} type={type} value={values[key]} onChange={set(key)} autoComplete="off" />
    </div>
  )

  return (
    <form onSubmit={submit} className="grid gap-4" noValidate>
      <FormErrorSummary message={save.error ? formError(save.error) : null} />
      {save.isSuccess ? <p className="text-sm text-success">{t('integrations.imports.connection.rest.saved')}</p> : null}
      <div className="grid gap-4 sm:grid-cols-2">
        {field('baseUrl', t('integrations.imports.connection.rest.baseUrl'), 'url')}
        <div className="grid gap-1.5">
          <Label htmlFor="rest-authType">{t('integrations.imports.connection.rest.authType')}</Label>
          <select id="rest-authType" className="h-9 rounded-md border bg-background px-2 text-sm" value={values.authType} onChange={set('authType')}>
            {AUTH_TYPES.map((type) => <option key={type} value={type}>{t(`integrations.imports.connection.rest.auth.${type}`)}</option>)}
          </select>
        </div>
        <div className="grid gap-1.5 sm:col-span-2">
          {field('credentials', t('integrations.imports.connection.rest.credentials'), 'password')}
          <p className="text-xs text-muted-foreground">
            {connection.hasCredentials ? t('integrations.imports.connection.rest.credentialsStored') : null} {t('integrations.imports.connection.rest.credentialsHint')}
          </p>
        </div>
        {field('path', t('integrations.imports.connection.rest.path'))}
        {field('healthPath', t('integrations.imports.connection.rest.healthPath'))}
        {field('recordsPath', t('integrations.imports.connection.rest.recordsPath'))}
        {field('sinceParam', t('integrations.imports.connection.rest.sinceParam'))}
        <div className="grid gap-1.5">
          <Label htmlFor="rest-paginationType">{t('integrations.imports.connection.rest.pagination')}</Label>
          <select id="rest-paginationType" className="h-9 rounded-md border bg-background px-2 text-sm" value={values.paginationType} onChange={set('paginationType')}>
            {PAGINATION_TYPES.map((type) => <option key={type} value={type}>{t(`integrations.imports.connection.rest.paginationTypes.${type}`)}</option>)}
          </select>
        </div>
        {values.paginationType !== 'none' ? field('pageParam', t('integrations.imports.connection.rest.pageParam')) : null}
        {values.paginationType !== 'none' ? field('pageSize', t('integrations.imports.connection.rest.pageSize'), 'number') : null}
        {field('actorUserId', t('integrations.imports.connection.rest.actorUserId'))}
      </div>
      <div className="flex justify-end">
        <Button type="submit" disabled={save.isPending || values.baseUrl === ''}>{t('integrations.imports.connection.rest.save')}</Button>
      </div>
    </form>
  )
}

/** Les erreurs de champ du backend, à plat : c'est lui qui valide la connexion. */
function formError(error: Error): string {
  if (!(error instanceof ApiError)) return error.message

  return [error.message, ...Object.values(error.errors).flat()].join(' ')
}

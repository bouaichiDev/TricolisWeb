import { useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

import { useSaveImportConnection } from '../hooks/useImportConnection'
import type { FileConnectionPayload, ImportConnectionState } from '../types/importConnection'

type FileConnection = Extract<NonNullable<ImportConnectionState['connection']>, { kind: 'file' }>

const AFTER_IMPORT = ['archive', 'delete', 'keep'] as const

/**
 * La connexion FTP/SFTP d'une relève. Le protocole découle du type de source.
 * Le mot de passe est **en écriture seule** : laissé vide, celui qui est
 * enregistré est gardé ; « Effacer » le supprime.
 */
export function FileConnectionForm({ configurationId, connection }: { configurationId: string; connection: FileConnection }) {
  const { t } = useTranslation()
  const save = useSaveImportConnection(configurationId)
  const [values, setValues] = useState({
    host: connection.host ?? '',
    port: connection.port ? String(connection.port) : '',
    username: connection.username ?? '',
    password: '',
    directory: connection.directory ?? '',
    filePattern: connection.filePattern ?? '',
    afterImport: connection.afterImport ?? 'archive',
    archiveDirectory: connection.archiveDirectory ?? '',
    actorEmail: connection.actorEmail ?? '',
  })
  const set = (key: keyof typeof values) => (event: { target: { value: string } }) => setValues((current) => ({ ...current, [key]: event.target.value }))

  const payload = (password?: string | null): FileConnectionPayload => ({
    host: values.host.trim(),
    port: values.port === '' ? null : Number(values.port),
    username: values.username || null,
    directory: values.directory || null,
    filePattern: values.filePattern || null,
    afterImport: values.afterImport as FileConnectionPayload['afterImport'],
    archiveDirectory: values.afterImport === 'archive' ? values.archiveDirectory || null : null,
    actorEmail: values.actorEmail || null,
    ...(password !== undefined ? { password } : {}),
  })

  const submit = (event: FormEvent) => {
    event.preventDefault()
    save.mutate(payload(values.password !== '' ? values.password : undefined), { onSuccess: () => setValues((current) => ({ ...current, password: '' })) })
  }

  const field = (key: keyof typeof values, label: string, type = 'text') => (
    <div className="grid gap-1.5">
      <Label htmlFor={`file-${key}`}>{label}</Label>
      <Input id={`file-${key}`} type={type} value={values[key]} onChange={set(key)} autoComplete="off" />
    </div>
  )

  return (
    <form onSubmit={submit} className="grid gap-4" noValidate>
      <FormErrorSummary message={save.error ? formError(save.error) : null} />
      {save.isSuccess ? <p className="text-sm text-success">{t('integrations.imports.connection.file.saved')}</p> : null}
      {connection.problem ? <p className="text-sm text-destructive">{connection.problem}</p> : null}
      <div className="grid gap-4 sm:grid-cols-2">
        {field('host', t('integrations.imports.connection.file.host'))}
        {field('port', t('integrations.imports.connection.file.port'), 'number')}
        {field('username', t('integrations.imports.connection.file.username'))}
        <div className="grid gap-1.5">
          {field('password', t('integrations.imports.connection.file.password'), 'password')}
          <p className="text-xs text-muted-foreground">
            {connection.hasCredentials ? t('integrations.imports.connection.file.passwordStored') : null} {t('integrations.imports.connection.file.passwordHint')}
          </p>
        </div>
        {field('directory', t('integrations.imports.connection.file.directory'))}
        {field('filePattern', t('integrations.imports.connection.file.pattern'))}
        <div className="grid gap-1.5">
          <Label htmlFor="file-afterImport">{t('integrations.imports.connection.file.afterImport')}</Label>
          <select id="file-afterImport" className="h-9 rounded-md border bg-background px-2 text-sm" value={values.afterImport} onChange={set('afterImport')}>
            {AFTER_IMPORT.map((after) => <option key={after} value={after}>{t(`integrations.imports.connection.file.afterOptions.${after}`)}</option>)}
          </select>
        </div>
        {values.afterImport === 'archive' ? field('archiveDirectory', t('integrations.imports.connection.file.archiveDirectory')) : null}
        {field('actorEmail', t('integrations.imports.connection.file.actorEmail'), 'email')}
      </div>
      <div className="flex flex-wrap justify-end gap-2">
        {connection.hasCredentials ? (
          <Button type="button" variant="outline" disabled={save.isPending || values.host === ''} onClick={() => save.mutate(payload(null))}>
            {t('integrations.imports.connection.file.clearPassword')}
          </Button>
        ) : null}
        <Button type="submit" disabled={save.isPending || values.host.trim() === ''}>{t('integrations.imports.connection.file.save')}</Button>
      </div>
    </form>
  )
}

/** Les erreurs de champ du backend, à plat : c'est lui qui valide la connexion. */
function formError(error: Error): string {
  if (!(error instanceof ApiError)) return error.message

  return [error.message, ...Object.values(error.errors).flat()].join(' ')
}

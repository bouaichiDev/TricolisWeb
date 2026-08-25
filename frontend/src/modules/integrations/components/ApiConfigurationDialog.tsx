import { useState } from 'react'
import { useTranslation } from 'react-i18next'

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

import { ApiCallFields } from './ApiCallFields'
import { useCreateApiConfiguration, useUpdateApiConfiguration } from '../hooks/useApiConfigurations'
import { AUTH_TYPES, type ApiConfiguration } from '../types/apiConfiguration'

interface ApiConfigurationDialogProps {
  /** `null` pour une création. */
  configuration: ApiConfiguration | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Déclaration d'une API externe.
 *
 * **Le secret ne se relit pas.** En modification, le champ est vide : le
 * laisser tel quel conserve celui en place, le remplir le remplace. Afficher
 * l'existant le ferait circuler dans une réponse, un cache, une capture
 * d'écran — pour rien, puisqu'on ne le consulte jamais, on le remplace.
 *
 * L'adresse doit être en HTTPS, et c'est le serveur qui tranche : un secret
 * envoyé en clair n'en est plus un.
 */
export function ApiConfigurationDialog({
  configuration,
  open,
  onOpenChange,
}: ApiConfigurationDialogProps) {
  const { t } = useTranslation()
  const isEdit = configuration !== null

  const [code, setCode] = useState(configuration?.code ?? '')
  const [name, setName] = useState(configuration?.name ?? '')
  const [baseUrl, setBaseUrl] = useState(configuration?.baseUrl ?? 'https://')
  const [authType, setAuthType] = useState(configuration?.authType ?? 'none')
  const [credentials, setCredentials] = useState('')
  const [call, setCall] = useState({
    path: configuration?.settings?.path ?? '',
    queryKey: configuration?.settings?.queryKey ?? '',
    queryTemplate: configuration?.settings?.queryTemplate ?? '',
  })
  const [isActive, setIsActive] = useState(configuration?.isActive ?? true)
  const [error, setError] = useState<string | null>(null)

  const create = useCreateApiConfiguration()
  const update = useUpdateApiConfiguration()

  const incomplete = code.trim() === '' || name.trim() === '' || baseUrl.trim() === ''

  const submit = async () => {
    setError(null)

    // Un secret laisse vide en modification conserve celui en place : l'envoyer
    // a null l'effacerait, ce que personne n'a demande en ne tapant rien.
    const secret = credentials.trim() === '' ? undefined : credentials.trim()

    try {
      const payload = {
        code: code.trim(),
        name: name.trim(),
        baseUrl: baseUrl.trim(),
        authType,
        isActive,
        // Un champ vide part a null : garder une chaine vide ferait construire
        // une adresse tronquee au lieu de renoncer a l'appel.
        settings: {
          path: call.path.trim() === '' ? null : call.path.trim(),
          queryKey: call.queryKey.trim() === '' ? null : call.queryKey.trim(),
          queryTemplate: call.queryTemplate.trim() === '' ? null : call.queryTemplate.trim(),
        },
        ...(secret === undefined ? {} : { credentials: secret }),
      }

      if (isEdit) await update.mutateAsync({ id: configuration.id, ...payload })
      else await create.mutateAsync(payload)

      onOpenChange(false)
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>
            {isEdit ? t('apiConfigurations.edit') : t('apiConfigurations.create')}
          </DialogTitle>
          <DialogDescription>{t('apiConfigurations.formHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={error} />

        <div className="flex flex-col gap-5">
          <div className="grid gap-4 sm:grid-cols-2">
            <ControlledField
              label={t('apiConfigurations.fields.code')}
              value={code}
              onChange={setCode}
              required
              description={t('apiConfigurations.codeHint')}
            />
            <ControlledField
              label={t('apiConfigurations.fields.name')}
              value={name}
              onChange={setName}
              required
            />
          </div>

          <ControlledField
            label={t('apiConfigurations.fields.baseUrl')}
            value={baseUrl}
            onChange={setBaseUrl}
            required
            description={t('apiConfigurations.baseUrlHint')}
          />

          <AsyncSelect
            label={t('apiConfigurations.fields.authType')}
            value={authType}
            onChange={(value) => setAuthType(value as ApiConfiguration['authType'])}
            options={AUTH_TYPES.map((type) => ({
              value: type,
              label: t(`authTypes.${type}`),
            }))}
            required
          />

          {authType === 'none' ? null : (
            <ControlledField
              label={t('apiConfigurations.fields.credentials')}
              value={credentials}
              onChange={setCredentials}
              placeholder={
                isEdit && configuration.hasCredentials
                  ? t('apiConfigurations.secretKept')
                  : undefined
              }
              description={t('apiConfigurations.secretHint')}
            />
          )}

          <ApiCallFields
            value={call}
            onChange={(patch) => setCall((current) => ({ ...current, ...patch }))}
          />

          <span className="flex items-center gap-2">
            <Switch id="api-active" checked={isActive} onCheckedChange={setIsActive} />
            <Label htmlFor="api-active">{t('apiConfigurations.fields.isActive')}</Label>
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

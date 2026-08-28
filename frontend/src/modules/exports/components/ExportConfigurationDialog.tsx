import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import {
  INVOICE_EXPORT_TYPE,
  INVOICE_FORMATS,
  INVOICE_TRANSPORTS,
  ON_INVOICE_CLOSED,
  needsHost,
  type ExportConfiguration,
  type ExportConfigurationPayload,
} from '../types/export'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { Switch } from '@/shared/components/ui/switch'

interface ExportConfigurationDialogProps {
  customerId: string
  configuration: ExportConfiguration | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSubmit: (payload: ExportConfigurationPayload) => void
  isPending: boolean
}

/**
 * Une destination d'export, créée ou corrigée.
 *
 * **Le mot de passe ne se relit pas.** Le serveur ne le rend jamais ; le champ
 * est donc vide à l'ouverture, et un champ laissé vide veut dire « inchangé »,
 * pas « effacé ». C'est ce que le §124 impose, et l'écran le dit plutôt que de
 * laisser croire à un oubli.
 *
 * Les formats et transports proposés sont ceux qu'on sait réellement produire
 * pour une facture : offrir CSV créerait une destination qui échoue à chaque
 * clôture, loin de l'écran qui l'a acceptée.
 */
export function ExportConfigurationDialog({
  customerId,
  configuration,
  open,
  onOpenChange,
  onSubmit,
  isPending,
}: ExportConfigurationDialogProps) {
  const { t } = useTranslation()

  const [name, setName] = useState(configuration?.name ?? '')
  const [format, setFormat] = useState(configuration?.format ?? 'json')
  const [transport, setTransport] = useState(configuration?.transport ?? 'rest_api')
  const [host, setHost] = useState(configuration?.host ?? '')
  const [port, setPort] = useState(configuration?.port?.toString() ?? '')
  const [username, setUsername] = useState(configuration?.username ?? '')
  const [password, setPassword] = useState('')
  const [remoteDirectory, setRemoteDirectory] = useState(configuration?.remoteDirectory ?? '')
  const [fileNamePattern, setFileNamePattern] = useState(configuration?.fileNamePattern ?? '')
  const [isActive, setIsActive] = useState(configuration?.isActive ?? true)

  const ready = name.trim() !== '' && (!needsHost(transport) || host.trim() !== '')

  const submit = () => {
    onSubmit({
      customerId,
      name: name.trim(),
      exportType: INVOICE_EXPORT_TYPE,
      format,
      transport,
      host: host.trim() || null,
      port: port === '' ? null : Number.parseInt(port, 10),
      username: username.trim() || null,
      // Un champ vide laisse le secret en place : l'omettre est la seule facon
      // de dire « inchange ».
      ...(password === '' ? {} : { password }),
      remoteDirectory: remoteDirectory.trim() || null,
      fileNamePattern: fileNamePattern.trim() || null,
      frequency: ON_INVOICE_CLOSED,
      isActive,
    })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {configuration ? t('exports.configurations.edit') : t('exports.configurations.create')}
          </DialogTitle>
          <DialogDescription>{t('exports.configurations.hint')}</DialogDescription>
        </DialogHeader>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-2 sm:col-span-2">
            <Label htmlFor="export-name">{t('exports.configurations.fields.name')}</Label>
            <Input id="export-name" value={name} onChange={(e) => setName(e.target.value)} required />
          </div>

          <AsyncSelect
            label={t('exports.configurations.fields.transport')}
            value={transport}
            onChange={setTransport}
            options={INVOICE_TRANSPORTS.map((value) => ({
              value,
              label: t(`exports.transports.${value}`),
            }))}
          />

          <AsyncSelect
            label={t('exports.configurations.fields.format')}
            value={format}
            onChange={setFormat}
            options={INVOICE_FORMATS.map((value) => ({ value, label: value.toUpperCase() }))}
          />

          <div className="flex flex-col gap-2">
            <Label htmlFor="export-host">{t('exports.configurations.fields.host')}</Label>
            <Input
              id="export-host"
              value={host}
              onChange={(e) => setHost(e.target.value)}
              placeholder={transport === 'rest_api' ? 'https://…' : ''}
              required={needsHost(transport)}
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="export-port">{t('exports.configurations.fields.port')}</Label>
            <Input
              id="export-port"
              type="number"
              min={1}
              max={65535}
              value={port}
              onChange={(e) => setPort(e.target.value)}
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="export-username">{t('exports.configurations.fields.username')}</Label>
            <Input
              id="export-username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="export-password">{t('exports.configurations.fields.password')}</Label>
            <Input
              id="export-password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder={
                configuration?.hasPassword
                  ? t('exports.configurations.passwordSet')
                  : t('exports.configurations.passwordEmpty')
              }
            />
            <p className="text-xs text-muted-foreground">
              {t('exports.configurations.passwordHint')}
            </p>
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="export-directory">{t('exports.configurations.fields.directory')}</Label>
            <Input
              id="export-directory"
              value={remoteDirectory}
              onChange={(e) => setRemoteDirectory(e.target.value)}
              disabled={transport === 'rest_api'}
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="export-pattern">{t('exports.configurations.fields.pattern')}</Label>
            <Input
              id="export-pattern"
              value={fileNamePattern}
              onChange={(e) => setFileNamePattern(e.target.value)}
              placeholder="{invoiceNumber}_{invoiceDate}"
            />
          </div>

          <div className="flex items-center gap-3 sm:col-span-2">
            <Switch id="export-active" checked={isActive} onCheckedChange={setIsActive} />
            <Label htmlFor="export-active">{t('exports.configurations.fields.isActive')}</Label>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button disabled={!ready || isPending} onClick={submit}>
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

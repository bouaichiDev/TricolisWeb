import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import {
  INVOICE_FORMATS,
  INVOICE_TRANSPORTS,
  needsHost,
  type AuthMode,
  type ExportConfiguration,
  type ExportConfigurationPayload,
  type ExportSettings,
} from '../types/export'
import {
  buildExportPayload,
  hasConnection,
  type ConnectionDraft,
} from '../utils/exportPayload'
import { ExportAuthFields } from './ExportAuthFields'
import { ExportConnectionFields } from './ExportConnectionFields'
import { ExportDeliveryFields } from './ExportDeliveryFields'
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
 * L'écran se réduit à ce que le transport et le format retenus demandent
 * vraiment : une connexion pour FTP et REST, une authentification pour REST
 * seul, des destinataires pour le courriel, un séparateur pour le CSV. Tout
 * afficher d'un coup donnerait une vingtaine de champs dont la moitié serait
 * sans effet — et remplie quand même.
 *
 * Les quatre formats et les cinq transports du modèle sont proposés : chacun a
 * désormais son générateur et son transporteur, et aucun ne crée une
 * destination qui échouerait à la première clôture.
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
  const [fileNamePattern, setFileNamePattern] = useState(configuration?.fileNamePattern ?? '')
  const [isActive, setIsActive] = useState(configuration?.isActive ?? true)
  const [connection, setConnection] = useState<ConnectionDraft>({
    host: configuration?.host ?? '',
    port: configuration?.port?.toString() ?? '',
    username: configuration?.username ?? '',
    password: '',
    remoteDirectory: configuration?.remoteDirectory ?? '',
  })
  const [settings, setSettings] = useState<ExportSettings>(
    (configuration?.settings ?? {}) as ExportSettings,
  )

  const authMode = (settings.authType ?? 'bearer') as AuthMode
  const patch = (values: ExportSettings) => setSettings({ ...settings, ...values })

  const ready =
    name.trim() !== '' &&
    (!needsHost(transport) || connection.host.trim() !== '') &&
    (transport !== 'email' || (settings.recipients ?? '').trim() !== '')

  const submit = () =>
    onSubmit(
      buildExportPayload(customerId, {
        name,
        format,
        transport,
        fileNamePattern,
        isActive,
        connection,
        settings,
      }),
    )

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
            <Input
              id="export-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
            />
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

          {transport === 'rest_api' ? (
            <ExportAuthFields settings={settings} onChange={patch} />
          ) : null}

          {hasConnection(transport) ? (
            <ExportConnectionFields
              transport={transport}
              authMode={authMode}
              host={connection.host}
              port={connection.port}
              username={connection.username}
              password={connection.password}
              remoteDirectory={connection.remoteDirectory}
              hasPassword={configuration?.hasPassword ?? false}
              onChange={(values) => setConnection({ ...connection, ...values })}
            />
          ) : null}

          <ExportDeliveryFields
            transport={transport}
            format={format}
            settings={settings}
            onChange={patch}
          />

          <div className="flex flex-col gap-2 sm:col-span-2">
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

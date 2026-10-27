import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

import { INVOICE_FORMATS, INVOICE_TRANSPORTS, type ExportSettings } from '../types/export'
import { EXPORT_CONTENTS, type ExportContent } from '../types/exportContents'

interface ExportTypeFieldsProps {
  content: ExportContent
  transport: string
  format: string
  settings: ExportSettings
  onContentChange: (content: ExportContent) => void
  onTransportChange: (transport: string) => void
  onFormatChange: (format: string) => void
  onSettingsChange: (values: ExportSettings) => void
}

/**
 * Ce qui part, par où, sous quelle forme — et s'il faut réessayer.
 *
 * Deux contenus : la facture clôturée, et le **statut de commande** poussé à
 * chaque changement (REST Push, Phase 6 portail), qui ne s'écrit qu'en JSON.
 * La relance automatique ne vaut que pour une panne passagère (délai, 429,
 * 5xx) ; sans réglage, une seule tentative, comme avant.
 */
export function ExportTypeFields(props: ExportTypeFieldsProps) {
  const { t } = useTranslation()
  const formats = EXPORT_CONTENTS[props.content].formats ?? INVOICE_FORMATS
  const retry = (props.settings.retry ?? {}) as { maxAttempts?: number; backoffSeconds?: number }
  const setRetry = (key: 'maxAttempts' | 'backoffSeconds', value: string) =>
    props.onSettingsChange({ retry: { ...retry, [key]: value === '' ? undefined : Number.parseInt(value, 10) } })

  return (
    <>
      <div className="sm:col-span-2">
        <AsyncSelect
          label={t('exports.configurations.fields.content')}
          value={props.content}
          onChange={(value) => props.onContentChange(value as ExportContent)}
          options={Object.keys(EXPORT_CONTENTS).map((value) => ({ value, label: t(`exports.contents.${value}`) }))}
        />
      </div>

      <AsyncSelect
        label={t('exports.configurations.fields.transport')}
        value={props.transport}
        onChange={props.onTransportChange}
        options={INVOICE_TRANSPORTS.map((value) => ({ value, label: t(`exports.transports.${value}`) }))}
      />

      <AsyncSelect
        label={t('exports.configurations.fields.format')}
        value={props.format}
        onChange={props.onFormatChange}
        options={formats.map((value) => ({ value, label: value.toUpperCase() }))}
      />

      {props.transport === 'rest_api' ? (
        <>
          <div className="flex flex-col gap-2">
            <Label htmlFor="export-retry-attempts">{t('exports.configurations.fields.retryAttempts')}</Label>
            <Input id="export-retry-attempts" type="number" min={1} max={5} value={retry.maxAttempts ?? ''} onChange={(e) => setRetry('maxAttempts', e.target.value)} placeholder="1" />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="export-retry-backoff">{t('exports.configurations.fields.retryBackoff')}</Label>
            <Input id="export-retry-backoff" type="number" min={10} max={3600} value={retry.backoffSeconds ?? ''} onChange={(e) => setRetry('backoffSeconds', e.target.value)} placeholder="60" />
          </div>
        </>
      ) : null}
    </>
  )
}

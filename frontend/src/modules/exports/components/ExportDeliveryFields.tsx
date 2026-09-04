import { useTranslation } from 'react-i18next'

import type { ExportSettings } from '../types/export'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { Textarea } from '@/shared/components/ui/textarea'

interface ExportDeliveryFieldsProps {
  transport: string
  format: string
  settings: ExportSettings
  onChange: (patch: ExportSettings) => void
}

/**
 * Ce que le transport et le format demandent en plus.
 *
 * Un envoi par courriel a besoin d'adresses ; un CSV, d'un séparateur ; un PDF,
 * d'un titre. Rien de tout cela n'a de colonne au modèle — le §31 l'interdit —
 * et tout vit donc dans `settings`, que le §66 prévoit pour ça.
 *
 * Chaque bloc ne paraît que si son transport ou son format est retenu : montrer
 * un séparateur CSV à qui exporte en JSON n'apprend rien et fait douter.
 */
export function ExportDeliveryFields({
  transport,
  format,
  settings,
  onChange,
}: ExportDeliveryFieldsProps) {
  const { t } = useTranslation()

  return (
    <>
      {transport === 'email' ? (
        <>
          <div className="flex flex-col gap-2 sm:col-span-2">
            <Label htmlFor="export-recipients">
              {t('exports.configurations.fields.recipients')}
            </Label>
            <Input
              id="export-recipients"
              value={settings.recipients ?? ''}
              onChange={(e) => onChange({ recipients: e.target.value })}
              placeholder="compta@client.fr, factures@client.fr"
              required
            />
            <p className="text-xs text-muted-foreground">
              {t('exports.configurations.recipientsHint')}
            </p>
          </div>

          <div className="flex flex-col gap-2 sm:col-span-2">
            <Label htmlFor="export-subject">{t('exports.configurations.fields.subject')}</Label>
            <Input
              id="export-subject"
              value={settings.subject ?? ''}
              onChange={(e) => onChange({ subject: e.target.value })}
            />
          </div>

          <div className="flex flex-col gap-2 sm:col-span-2">
            <Label htmlFor="export-body">{t('exports.configurations.fields.body')}</Label>
            <Textarea
              id="export-body"
              rows={3}
              value={settings.body ?? ''}
              onChange={(e) => onChange({ body: e.target.value })}
            />
          </div>
        </>
      ) : null}

      {format === 'csv' ? (
        <>
          <div className="flex flex-col gap-2">
            <Label htmlFor="export-delimiter">
              {t('exports.configurations.fields.delimiter')}
            </Label>
            <Input
              id="export-delimiter"
              maxLength={1}
              value={settings.delimiter ?? ''}
              onChange={(e) => onChange({ delimiter: e.target.value })}
              placeholder=";"
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="export-enclosure">
              {t('exports.configurations.fields.enclosure')}
            </Label>
            <Input
              id="export-enclosure"
              maxLength={1}
              value={settings.enclosure ?? ''}
              onChange={(e) => onChange({ enclosure: e.target.value })}
              placeholder={'"'}
            />
          </div>
        </>
      ) : null}

      {format === 'pdf' ? (
        <div className="flex flex-col gap-2 sm:col-span-2">
          <Label htmlFor="export-doc-title">
            {t('exports.configurations.fields.documentTitle')}
          </Label>
          <Input
            id="export-doc-title"
            value={settings.documentTitle ?? ''}
            onChange={(e) => onChange({ documentTitle: e.target.value })}
            placeholder={t('exports.configurations.documentTitlePlaceholder')}
          />
        </div>
      ) : null}
    </>
  )
}

import { useTranslation } from 'react-i18next'

import { AUTH_MODES, type AuthMode, type ExportSettings } from '../types/export'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

interface ExportAuthFieldsProps {
  settings: ExportSettings
  onChange: (patch: ExportSettings) => void
}

/**
 * Comment la requête sortante s'authentifie chez le client.
 *
 * **Aucun secret ne se saisit ici.** Le jeton, la clé ou le mot de passe
 * passent tous par le champ dédié du dialogue, chiffré côté serveur ; ces
 * champs-ci ne portent que ce qui n'est pas confidentiel — un mode, un nom
 * d'en-tête, une URL. Mélanger les deux ferait atterrir un secret dans
 * `settings`, que le serveur renvoie en clair à l'écran.
 *
 * Les champs suivent le mode : proposer une URL de jeton à qui a choisi
 * `basic` ne ferait qu'inviter à la remplir pour rien.
 */
export function ExportAuthFields({ settings, onChange }: ExportAuthFieldsProps) {
  const { t } = useTranslation()
  const mode = (settings.authType ?? 'bearer') as AuthMode

  return (
    <>
      <AsyncSelect
        label={t('exports.configurations.fields.authType')}
        value={mode}
        onChange={(value) => onChange({ authType: value as AuthMode })}
        options={AUTH_MODES.map((value) => ({
          value,
          label: t(`exports.authModes.${value}`),
        }))}
      />

      {mode === 'api_key' ? (
        <div className="flex flex-col gap-2">
          <Label htmlFor="export-api-header">
            {t('exports.configurations.fields.apiKeyHeader')}
          </Label>
          <Input
            id="export-api-header"
            value={settings.apiKeyHeader ?? ''}
            onChange={(e) => onChange({ apiKeyHeader: e.target.value })}
            placeholder="X-Api-Key"
          />
        </div>
      ) : null}

      {mode === 'oauth2' ? (
        <>
          <div className="flex flex-col gap-2 sm:col-span-2">
            <Label htmlFor="export-token-url">
              {t('exports.configurations.fields.tokenUrl')}
            </Label>
            <Input
              id="export-token-url"
              value={settings.tokenUrl ?? ''}
              onChange={(e) => onChange({ tokenUrl: e.target.value })}
              placeholder="https://…/oauth/token"
            />
            <p className="text-xs text-muted-foreground">
              {t('exports.configurations.oauthHint')}
            </p>
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="export-client-id">
              {t('exports.configurations.fields.clientId')}
            </Label>
            <Input
              id="export-client-id"
              value={settings.clientId ?? ''}
              onChange={(e) => onChange({ clientId: e.target.value })}
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="export-scope">{t('exports.configurations.fields.scope')}</Label>
            <Input
              id="export-scope"
              value={settings.scope ?? ''}
              onChange={(e) => onChange({ scope: e.target.value })}
            />
          </div>
        </>
      ) : null}
    </>
  )
}

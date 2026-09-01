import { useTranslation } from 'react-i18next'

import { needsHost, secretLabelKey, type AuthMode } from '../types/export'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

interface ExportConnectionFieldsProps {
  transport: string
  authMode: AuthMode
  host: string
  port: string
  username: string
  password: string
  remoteDirectory: string
  hasPassword: boolean
  onChange: (patch: Record<string, string>) => void
}

/**
 * De quoi joindre le serveur du client.
 *
 * **Le mot de passe ne se relit pas.** Le serveur ne rend que `hasPassword` :
 * le §124 interdit de renvoyer un secret, fût-ce à celui qui l'a saisi. Un
 * champ laissé vide veut donc dire « inchangé », jamais « effacé » — et
 * l'espace réservé le dit, plutôt que de laisser croire à un oubli.
 *
 * Son libellé suit le mode d'authentification : ce qu'on colle pour un OAuth2
 * est un secret client, pour `api_key` une clé. Les appeler tous « mot de
 * passe » ferait chercher un champ qui n'existe pas.
 *
 * En mode `none`, aucun secret n'est demandé : l'URL porte tout.
 */
export function ExportConnectionFields({
  transport,
  authMode,
  host,
  port,
  username,
  password,
  remoteDirectory,
  hasPassword,
  onChange,
}: ExportConnectionFieldsProps) {
  const { t } = useTranslation()
  const rest = transport === 'rest_api'
  const secret = secretLabelKey(transport, authMode)

  return (
    <>
      <div className="flex flex-col gap-2">
        <Label htmlFor="export-host">
          {t(rest ? 'exports.configurations.fields.url' : 'exports.configurations.fields.host')}
        </Label>
        <Input
          id="export-host"
          value={host}
          onChange={(e) => onChange({ host: e.target.value })}
          placeholder={rest ? 'https://…' : ''}
          required={needsHost(transport)}
        />
      </div>

      {rest ? null : (
        <div className="flex flex-col gap-2">
          <Label htmlFor="export-port">{t('exports.configurations.fields.port')}</Label>
          <Input
            id="export-port"
            type="number"
            min={1}
            max={65535}
            value={port}
            onChange={(e) => onChange({ port: e.target.value })}
          />
        </div>
      )}

      <div className="flex flex-col gap-2">
        <Label htmlFor="export-username">
          {t(
            rest && authMode === 'oauth2'
              ? 'exports.configurations.fields.clientId'
              : 'exports.configurations.fields.username',
          )}
        </Label>
        <Input
          id="export-username"
          value={username}
          onChange={(e) => onChange({ username: e.target.value })}
        />
      </div>

      {rest && authMode === 'none' ? null : (
        <div className="flex flex-col gap-2">
          <Label htmlFor="export-password">
            {t(`exports.configurations.fields.${secret}`)}
          </Label>
          <Input
            id="export-password"
            type="password"
            value={password}
            onChange={(e) => onChange({ password: e.target.value })}
            placeholder={
              hasPassword
                ? t('exports.configurations.passwordSet')
                : t('exports.configurations.passwordEmpty')
            }
          />
          <p className="text-xs text-muted-foreground">
            {t('exports.configurations.passwordHint')}
          </p>
        </div>
      )}

      {rest ? null : (
        <div className="flex flex-col gap-2">
          <Label htmlFor="export-directory">
            {t('exports.configurations.fields.directory')}
          </Label>
          <Input
            id="export-directory"
            value={remoteDirectory}
            onChange={(e) => onChange({ remoteDirectory: e.target.value })}
          />
        </div>
      )}
    </>
  )
}

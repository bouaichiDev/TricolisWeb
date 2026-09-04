import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import type { OrderCommunication } from '../types/communication'

/**
 * D'où vient ce message, et avec quelles valeurs.
 *
 * **L'origine se déduit, elle ne se stocke pas.** `OrderCommunication` n'a pas
 * de champ `origin`, et le §75 interdit d'en inventer un : une règle
 * renseignée dit « automatique », son absence dit « écrit par quelqu'un ».
 *
 * Le lien vers le modèle mène au modèle **actuel**, qui a pu changer. Le
 * contenu montré ailleurs dans ce tiroir reste l'instantané de l'envoi : le
 * §122 interdit de présenter le corps courant du modèle comme ce qui est parti.
 *
 * `templateVariables` est lui aussi un instantané : les valeurs réellement
 * employées, jamais recalculées (§61).
 */
export function CommunicationOriginPanel({
  communication,
}: {
  communication: OrderCommunication
}) {
  const { t } = useTranslation()

  const variables = Object.entries(communication.templateVariables ?? {})

  return (
    <section className="flex flex-col gap-3 border-t pt-3">
      <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
        {t('communications.fields.origin')}
      </p>

      <p className="text-sm">
        {communication.communicationRuleId === null
          ? t('communications.origins.manualHint')
          : t('communications.origins.ruleHint')}
      </p>

      <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        {communication.communicationRuleId !== null ? (
          <div className="contents">
            <dt className="text-muted-foreground">{t('communications.fields.rule')}</dt>
            <dd>
              <Link to="/communications/rules" className="underline-offset-2 hover:underline">
                {t('communications.origins.openRules')}
              </Link>
            </dd>
          </div>
        ) : null}

        {communication.templateId !== null ? (
          <div className="contents">
            <dt className="text-muted-foreground">{t('communications.fields.template')}</dt>
            <dd>
              <Link
                to={`/templates?templateType=${communication.communicationType}`}
                className="underline-offset-2 hover:underline"
              >
                {communication.template?.name ?? communication.templateId}
              </Link>
            </dd>
          </div>
        ) : null}

        <div className="contents">
          <dt className="text-muted-foreground">{t('communications.fields.createdBy')}</dt>
          {/* Une communication automatique n'a pas d'auteur : `createdBy` est
              nul, et le §123 interdit d'inventer un faux utilisateur systeme
              en base — c'est un libelle d'ecran, pas une ligne de `users`. */}
          <dd>
            {communication.creator === undefined
              ? t('communications.origins.system')
              : `${communication.creator.firstName} ${communication.creator.lastName}`}
          </dd>
        </div>
      </dl>

      {variables.length > 0 ? (
        <div>
          <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {t('communications.fields.templateVariables')}
          </p>
          <ul className="flex flex-col gap-0.5 font-mono text-xs">
            {variables.map(([name, value]) => (
              <li key={name}>
                <span className="text-muted-foreground">{name}</span> = {String(value ?? '')}
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </section>
  )
}

import { useTranslation } from 'react-i18next'

import { Alert, AlertDescription } from '@/shared/components/ui/alert'

import { hasSubject } from '../types/communication'
import type { TemplateFormValues } from '../schemas/templateForm'

/**
 * Aperçu d'un modèle.
 *
 * Il montre le texte **tel quel**, accolades comprises. Aucun endpoint de rendu
 * n'existe côté serveur : substituer les variables ici inventerait un moteur de
 * template que le serveur ne connaît pas, et l'aperçu ne ressemblerait pas au
 * message reçu.
 *
 * C'est donc un aperçu de **mise en forme**, et il le dit. Les variables
 * déclarées sont soulignées visuellement pour qu'on repère celles qui ne sont
 * pas déclarées : le serveur ne les remplacerait pas.
 */
export function CommunicationTemplatePreview({ values }: { values: TemplateFormValues }) {
  const { t } = useTranslation()

  const used = [...values.bodyTemplate.matchAll(/\{\{\s*([\w.]+)\s*\}\}/g)].map(
    (match) => match[1],
  )
  const undeclared = [...new Set(used)].filter(
    (name) => !values.availableVariables.includes(name),
  )

  return (
    <section className="flex flex-col gap-2 border-t pt-4">
      <div>
        <p className="text-sm font-medium">{t('communicationTemplates.preview')}</p>
        <p className="text-xs text-muted-foreground">{t('communicationTemplates.previewHint')}</p>
      </div>

      {undeclared.length > 0 ? (
        <Alert>
          <AlertDescription>
            {t('communicationTemplates.undeclaredVariables', {
              names: undeclared.map((name) => `{{${name}}}`).join(', '),
            })}
          </AlertDescription>
        </Alert>
      ) : null}

      <div className="rounded-md border bg-muted/40 p-3">
        {hasSubject(values.channel) ? (
          <p className="mb-2 border-b pb-2 text-sm font-medium">
            {values.subjectTemplate === '' ? (
              <span className="text-muted-foreground">
                {t('communicationTemplates.emptySubject')}
              </span>
            ) : (
              values.subjectTemplate
            )}
          </p>
        ) : null}

        <p className="whitespace-pre-wrap text-sm">
          {values.bodyTemplate === '' ? (
            <span className="text-muted-foreground">{t('communicationTemplates.emptyBody')}</span>
          ) : (
            values.bodyTemplate
          )}
        </p>
      </div>
    </section>
  )
}

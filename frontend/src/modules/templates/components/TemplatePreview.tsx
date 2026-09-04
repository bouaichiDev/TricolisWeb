import { useTranslation } from 'react-i18next'

import { hasSubject } from '@/modules/communications/types/communication'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'

import type { TemplateFormValues } from '../schemas/templateSchema'
import { isDocumentType } from '../types/template'

/**
 * Chemins employés dans le corps, sections comprises.
 *
 * Tolère l'absence de corps : la liste des modèles n'en transporte pas, et un
 * appelant qui lui passerait une ligne de liste ferait sinon tomber l'écran
 * entier pour un aperçu.
 */
function usedPaths(body: string | undefined): string[] {
  if (body === undefined || body === '') return []

  const placeholders = [...body.matchAll(/\{\{\s*([\w.]+)\s*\}\}/g)].map((match) => match[1])
  const sections = [...body.matchAll(/\{\{#\s*([\w.]+)\s*\}\}/g)].map((match) => match[1])

  return [...new Set([...placeholders, ...sections])]
}

/**
 * Aperçu d'un modèle.
 *
 * Il montre le texte **tel quel**, accolades comprises. Aucun endpoint de rendu
 * de modèle n'existe : substituer les variables ici inventerait un moteur que
 * le serveur ne connaît pas, et l'aperçu ne ressemblerait pas au résultat.
 *
 * Une facture a, elle, un aperçu réel — depuis la facture, où les données
 * existent. Ici, il n'y a pas de facture à rendre.
 *
 * Les chemins non déclarés sont signalés : le serveur **refuse le rendu** quand
 * il en rencontre un, et le découvrir à la clôture d'une facture serait tard.
 */
export function TemplatePreview({ values }: { values: TemplateFormValues }) {
  const { t } = useTranslation()

  const undeclared = usedPaths(values.bodyTemplate).filter(
    (name) => !values.availableVariables.includes(name),
  )

  const showsSubject = !isDocumentType(values.templateType) && hasSubject(values.channel)

  return (
    <section className="flex flex-col gap-2 border-t pt-4">
      <div>
        <p className="text-sm font-medium">{t('templates.preview')}</p>
        <p className="text-xs text-muted-foreground">{t('templates.previewHint')}</p>
      </div>

      {undeclared.length > 0 ? (
        <Alert>
          <AlertDescription>
            {t('templates.undeclaredVariables', {
              names: undeclared.map((name) => `{{${name}}}`).join(', '),
            })}
          </AlertDescription>
        </Alert>
      ) : null}

      <div className="rounded-md border bg-muted/40 p-3">
        {showsSubject ? (
          <p className="mb-2 border-b pb-2 text-sm font-medium">
            {values.subjectTemplate === '' ? (
              <span className="text-muted-foreground">{t('templates.emptySubject')}</span>
            ) : (
              values.subjectTemplate
            )}
          </p>
        ) : null}

        {values.bodyTemplate === '' ? (
          <p className="text-sm text-muted-foreground">{t('templates.emptyBody')}</p>
        ) : values.bodyFormat === 'html' ? (
          /**
           * Un modèle HTML se lit mis en forme, sinon il faut l'enregistrer et
           * l'envoyer pour savoir ce qu'il donne.
           *
           * Le rendu passe par une **iframe cloisonnée** et non par
           * `dangerouslySetInnerHTML` : un `<script>` glissé dans un modèle
           * s'exécuterait sinon chez tous ceux qui l'ouvrent, avec leur session.
           * `sandbox` vide coupe scripts, formulaires et accès au parent ;
           * `srcDoc` évite toute requête réseau.
           */
          <iframe
            title={t('templates.preview')}
            sandbox=""
            srcDoc={values.bodyTemplate}
            className="h-48 w-full rounded border bg-background"
          />
        ) : (
          <p className="whitespace-pre-wrap text-sm">{values.bodyTemplate}</p>
        )}
      </div>
    </section>
  )
}

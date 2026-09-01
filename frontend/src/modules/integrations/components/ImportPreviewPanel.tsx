import { CircleAlert, CircleCheck, FlaskConical, Upload } from 'lucide-react'
import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation } from '@tanstack/react-query'

import { ApiError } from '@/shared/api/errors'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import { customerImportConfigurationsApi } from '../api/customer-import-configurations.api'
import type { ImportPreview } from '../types/customerIntegration'

interface ImportPreviewPanelProps {
  configurationId: string
  /** Sans correspondance, il n'y a rien à éprouver. */
  hasMapping: boolean
}

/**
 * Éprouver la correspondance sur un vrai fichier.
 *
 * C'est ce qui manquait pour qu'une configuration d'import soit utilisable :
 * on pouvait la décrire, jamais vérifier qu'elle était juste. Saisir une
 * correspondance à l'aveugle, puis la remettre à un client sans l'avoir
 * éprouvée, n'est pas une fonction — c'est un formulaire.
 *
 * **Rien n'est créé.** Le fichier est lu en mémoire par le serveur, la
 * correspondance appliquée, le résultat rendu. Aucune commande, aucune trace :
 * il n'existe toujours pas de moteur d'import, et prévisualiser n'est pas
 * importer.
 *
 * Trois choses se lisent ensuite, et chacune répond à une question distincte :
 * les **colonnes trouvées** disent si un nom est mal orthographié, la **charge
 * utile** montre ce que la correspondance produit vraiment, et le **verdict**
 * dit ce qui manquerait à la création d'une commande.
 */
export function ImportPreviewPanel({ configurationId, hasMapping }: ImportPreviewPanelProps) {
  const { t } = useTranslation()
  const input = useRef<HTMLInputElement>(null)
  const [fileName, setFileName] = useState<string | null>(null)

  const preview = useMutation({
    mutationFn: (file: File) => customerImportConfigurationsApi.preview(configurationId, file),
  })

  const run = (file: File) => {
    setFileName(file.name)
    preview.mutate(file)
  }

  const result: ImportPreview | undefined = preview.data
  const errors = Object.entries(result?.errors ?? {})

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-3">
        <input
          ref={input}
          type="file"
          className="hidden"
          onChange={(event) => {
            const file = event.target.files?.[0]
            if (file !== undefined) run(file)
            // Rejouer le même fichier après correction doit relancer l'essai :
            // sans cela, le champ garde sa valeur et n'émet plus rien.
            event.target.value = ''
          }}
        />

        <Button
          type="button"
          variant="outline"
          disabled={!hasMapping || preview.isPending}
          onClick={() => input.current?.click()}
        >
          <Upload className="size-4" aria-hidden />
          {preview.isPending ? t('common.loading') : t('integrations.imports.preview.choose')}
        </Button>

        {fileName === null ? (
          <span className="text-xs text-muted-foreground">
            {hasMapping
              ? t('integrations.imports.preview.hint')
              : t('integrations.imports.preview.noMapping')}
          </span>
        ) : (
          <span className="text-xs text-muted-foreground">{fileName}</span>
        )}
      </div>

      {preview.error === null || preview.error === undefined ? null : (
        <FormErrorSummary
          message={
            preview.error instanceof ApiError
              ? preview.error.message
              : t('errors.unexpected')
          }
        />
      )}

      {result === undefined ? null : (
        <div className="flex flex-col gap-4">
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="outline">
              {t('integrations.imports.preview.rows', { count: result.rowCount })}
            </Badge>

            {errors.length === 0 ? (
              <span className="flex items-center gap-1.5 text-sm text-success">
                <CircleCheck className="size-4" aria-hidden />
                {t('integrations.imports.preview.complete')}
              </span>
            ) : (
              <span className="flex items-center gap-1.5 text-sm text-destructive">
                <CircleAlert className="size-4" aria-hidden />
                {t('integrations.imports.preview.incomplete', { count: errors.length })}
              </span>
            )}
          </div>

          {/* Les colonnes lues : c'est là qu'on repère un nom mal orthographié
              dans la correspondance, avant de chercher ailleurs. */}
          <div>
            <p className="mb-1.5 text-xs font-medium uppercase text-muted-foreground">
              {t('integrations.imports.preview.columns')}
            </p>
            <ul className="flex flex-wrap gap-1">
              {result.columns.map((column) => (
                <li key={column}>
                  <Badge variant="secondary" className="font-mono text-[11px]">
                    {column}
                  </Badge>
                </li>
              ))}
            </ul>
          </div>

          {errors.length === 0 ? null : (
            <div>
              <p className="mb-1.5 text-xs font-medium uppercase text-muted-foreground">
                {t('integrations.imports.preview.missing')}
              </p>
              <ul className="flex flex-col gap-1 rounded-md border border-destructive/30 bg-destructive/5 p-3">
                {errors.map(([field, messages]) => (
                  <li key={field} className="text-xs">
                    <span className="font-mono">{field}</span>
                    <span className="text-muted-foreground"> — {messages[0]}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}

          <div>
            <p className="mb-1.5 text-xs font-medium uppercase text-muted-foreground">
              {t('integrations.imports.preview.payload')}
            </p>
            <pre className="max-h-80 overflow-auto rounded-md border bg-muted p-3 font-mono text-xs">
              {JSON.stringify(result.payload, null, 2)}
            </pre>
          </div>

          {/* Le point qu'on croirait un défaut : ces identifiants n'ont pas à
              venir du fichier, et le verdict ne les réclame donc pas. */}
          <p className="flex items-start gap-2 rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs">
            <FlaskConical className="mt-0.5 size-3.5 shrink-0 text-warning" aria-hidden />
            <span>
              {t('integrations.imports.preview.resolvedElsewhere', {
                fields: result.resolvedElsewhere.slice(0, 3).join(', '),
              })}
            </span>
          </p>
        </div>
      )}
    </div>
  )
}

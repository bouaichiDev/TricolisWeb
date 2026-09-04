import { ImageOff, Trash2, Upload } from 'lucide-react'
import { useRef } from 'react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import { Skeleton } from '@/shared/components/ui/skeleton'

interface LogoFieldProps {
  /** URL locale de l'image, ou `null` s'il n'y en a pas. */
  url: string | null
  hasLogo: boolean
  isLoading: boolean
  isBusy: boolean
  hint: string
  onUpload: (file: File) => void
  onRemove: () => void
}

/**
 * Déposer, remplacer ou retirer un logo.
 *
 * Le même geste pour l'organisation et pour la plateforme. Il vivait sur le
 * premier ; l'écran de configuration en avait besoin à l'identique, et le
 * recopier aurait suffi à ce que les deux divergent au premier ajustement —
 * l'un accepterait un format que l'autre refuse, sans qu'on sache lequel a
 * raison.
 *
 * **L'aperçu est posé sur un fond blanc.** C'est celui du papier pour un logo
 * d'organisation, et celui de la tuile pour un logo de plateforme : un logo qui
 * ne se lirait que sur fond sombre passerait inaperçu ici pour se perdre là-bas.
 */
export function LogoField({
  url,
  hasLogo,
  isLoading,
  isBusy,
  hint,
  onUpload,
  onRemove,
}: LogoFieldProps) {
  const { t } = useTranslation()
  const input = useRef<HTMLInputElement>(null)

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-muted-foreground">{hint}</p>

      <div className="flex flex-wrap items-center gap-6">
        <div className="flex size-32 items-center justify-center rounded-lg border bg-white p-2">
          {hasLogo && isLoading ? <Skeleton className="size-full" /> : null}

          {url === null && !(hasLogo && isLoading) ? (
            <ImageOff className="size-8 text-muted-foreground" aria-hidden />
          ) : null}

          {url !== null ? (
            <img src={url} alt={t('organizations.logo.title')} className="max-h-full max-w-full" />
          ) : null}
        </div>

        <div className="flex flex-col gap-2">
          {/* Le champ natif reste caché : son apparence n'est pas réglable, et
              il jurerait au milieu de boutons dessinés. */}
          <input
            ref={input}
            type="file"
            accept="image/png,image/jpeg,image/gif"
            className="hidden"
            aria-label={t('organizations.logo.choose')}
            onChange={(event) => {
              const file = event.target.files?.[0]
              if (file !== undefined) onUpload(file)
              // Remis à zéro : sans cela, redéposer le même fichier après une
              // erreur ne déclencherait aucun événement.
              event.target.value = ''
            }}
          />

          <Button variant="outline" disabled={isBusy} onClick={() => input.current?.click()}>
            <Upload className="size-4" aria-hidden />
            {hasLogo ? t('organizations.logo.replace') : t('organizations.logo.choose')}
          </Button>

          {hasLogo ? (
            <Button variant="ghost" disabled={isBusy} onClick={onRemove}>
              <Trash2 className="size-4" aria-hidden />
              {t('common.delete')}
            </Button>
          ) : null}

          <p className="text-xs text-muted-foreground">{t('organizations.logo.formats')}</p>
        </div>
      </div>
    </div>
  )
}

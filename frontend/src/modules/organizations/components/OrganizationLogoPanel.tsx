import { ImageOff, Trash2, Upload } from 'lucide-react'
import { useRef } from 'react'
import { useTranslation } from 'react-i18next'

import {
  useOrganizationLogo,
  useRemoveOrganizationLogo,
  useUploadOrganizationLogo,
} from '../hooks/useOrganizationLogo'
import { Button } from '@/shared/components/ui/button'
import { Skeleton } from '@/shared/components/ui/skeleton'

interface OrganizationLogoPanelProps {
  organizationId: string
  hasLogo: boolean
}

/**
 * Le logo de l'organisation, tel qu'il paraîtra sur ses documents.
 *
 * Il n'est pas décoratif : les modèles de facture l'écrivent
 * `<img src="{{ organization.logo }}">`, et le PDF l'embarque. L'aperçu est
 * donc posé sur un fond blanc — c'est celui du papier, et un logo qui ne se
 * lirait que sur fond sombre passerait inaperçu ici pour se perdre là-bas.
 *
 * Les formats offerts sont ceux que le moteur PDF sait poser sur une page :
 * PNG, JPEG, GIF. Le SVG, format naturel d'un logo, en est absent — il ne le
 * rend pas, et l'accepter donnerait des factures au logo manquant sans qu'une
 * erreur ne soit levée.
 */
export function OrganizationLogoPanel({ organizationId, hasLogo }: OrganizationLogoPanelProps) {
  const { t } = useTranslation()
  const input = useRef<HTMLInputElement>(null)

  const { url, isPending } = useOrganizationLogo(organizationId, hasLogo)
  const upload = useUploadOrganizationLogo(organizationId)
  const remove = useRemoveOrganizationLogo(organizationId)

  const busy = upload.isPending || remove.isPending

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-muted-foreground">{t('organizations.logo.hint')}</p>

      <div className="flex flex-wrap items-center gap-6">
        <div className="flex size-32 items-center justify-center rounded-lg border bg-white p-2">
          {hasLogo && isPending ? <Skeleton className="size-full" /> : null}

          {url === null && !(hasLogo && isPending) ? (
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
              if (file !== undefined) upload.mutate(file)
              // Remis à zéro : sans cela, redéposer le même fichier après une
              // erreur ne déclencherait aucun événement.
              event.target.value = ''
            }}
          />

          <Button variant="outline" disabled={busy} onClick={() => input.current?.click()}>
            <Upload className="size-4" aria-hidden />
            {hasLogo ? t('organizations.logo.replace') : t('organizations.logo.choose')}
          </Button>

          {hasLogo ? (
            <Button variant="ghost" disabled={busy} onClick={() => remove.mutate(undefined)}>
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

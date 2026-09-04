import { Check, Copy, TriangleAlert } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

interface ApiKeyCreatedDialogProps {
  /** La clé en clair, `null` quand le dialogue est fermé. */
  apiKey: string | null
  /** Nom de la configuration, pour dire de quelle clé il s'agit. */
  configurationName?: string
  onClose: () => void
}

/**
 * La clé API, montrée une seule fois.
 *
 * Le serveur ne stocke qu'un hash : cette valeur n'existe qu'ici, dans l'état
 * de ce composant, et **nulle part ailleurs**. Elle n'est pas mise au cache de
 * TanStack Query, pas écrite en `localStorage` ni en `sessionStorage`, pas
 * placée dans l'URL, pas journalisée (§22, §105). Fermer le dialogue la perd —
 * définitivement, et c'est le contrat.
 *
 * Le bouton de fermeture reste explicite plutôt que de laisser cliquer à côté :
 * on ne referme pas par mégarde une valeur irrécupérable.
 */
export function ApiKeyCreatedDialog({
  apiKey,
  configurationName,
  onClose,
}: ApiKeyCreatedDialogProps) {
  const { t } = useTranslation()
  const [copied, setCopied] = useState(false)
  const [failed, setFailed] = useState(false)

  /**
   * Le presse-papiers n'est pas toujours accessible : il exige un contexte
   * sécurisé, et une application servie en HTTP simple n'y a pas droit. Un
   * échec silencieux laisserait croire que la clé est copiée alors qu'elle ne
   * l'est pas — pour une valeur qu'on ne reverra jamais, c'est la pire issue
   * possible. On le dit, et on invite à la sélectionner à la main.
   */
  const copy = () => {
    if (apiKey === null) return

    setFailed(false)

    navigator.clipboard
      ?.writeText(apiKey)
      .then(() => {
        setCopied(true)
        setTimeout(() => setCopied(false), 2500)
      })
      .catch(() => setFailed(true)) ?? setFailed(true)
  }

  const close = () => {
    setCopied(false)
    setFailed(false)
    onClose()
  }

  return (
    <Dialog
      open={apiKey !== null}
      // Un clic à côté ne referme pas : la clé ne se retrouve pas.
      onOpenChange={(open) => {
        if (!open) close()
      }}
    >
      <DialogContent className="sm:max-w-xl" showCloseButton={false}>
        <DialogHeader>
          <DialogTitle>{t('integrations.api.keyTitle')}</DialogTitle>
          <DialogDescription>
            {configurationName ?? t('integrations.api.keyForNew')}
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <div className="flex items-start gap-3 rounded-md border border-warning/30 bg-warning/10 p-3">
            <TriangleAlert className="mt-0.5 size-4 shrink-0 text-warning" aria-hidden />
            <p className="text-sm">{t('integrations.api.keyWarning')}</p>
          </div>

          {/* La clé s'affiche **en entier**, sur plusieurs lignes s'il le faut.
              Un défilement horizontal en cacherait la moitié, et personne ne
              vérifie ce qu'il a copié dans une valeur tronquée. `select-all`
              permet de la prendre d'un clic quand le presse-papiers est
              indisponible. */}
          <code className="block select-all break-all rounded-md border bg-muted px-3 py-2.5 font-mono text-sm leading-relaxed">
            {apiKey}
          </code>

          <Button type="button" variant="outline" className="w-full" onClick={copy}>
            {copied ? (
              <Check className="size-4 text-success" aria-hidden />
            ) : (
              <Copy className="size-4" aria-hidden />
            )}
            {copied ? t('common.copied') : t('integrations.api.copyKey')}
          </Button>

          {failed ? (
            <p className="text-sm text-destructive">{t('integrations.api.copyFailed')}</p>
          ) : null}
        </div>

        <DialogFooter>
          <Button type="button" onClick={close}>
            {t('integrations.api.keySaved')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

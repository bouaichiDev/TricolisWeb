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

  const copy = () => {
    if (apiKey === null) return

    void navigator.clipboard.writeText(apiKey).then(() => {
      setCopied(true)
      setTimeout(() => setCopied(false), 2500)
    })
  }

  const close = () => {
    setCopied(false)
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
      <DialogContent className="max-w-lg" showCloseButton={false}>
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

          <div className="flex items-center gap-2">
            <code className="min-w-0 flex-1 overflow-x-auto rounded-md border bg-muted px-3 py-2 font-mono text-sm">
              {apiKey}
            </code>

            <Button type="button" variant="outline" onClick={copy} aria-label={t('common.copy')}>
              {copied ? (
                <Check className="size-4 text-success" aria-hidden />
              ) : (
                <Copy className="size-4" aria-hidden />
              )}
              {copied ? t('common.copied') : t('common.copy')}
            </Button>
          </div>
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

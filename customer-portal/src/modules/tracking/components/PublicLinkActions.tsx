import { Copy, Link2Off, RefreshCw } from 'lucide-react'
import { toast } from 'sonner'

import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'

interface PublicLinkActionsProps {
  publicUrl: string | null
  busy: boolean
  onRegenerate: () => void
  onRevoke: () => void
}

async function copy(url: string) {
  try {
    await navigator.clipboard.writeText(url)
    toast.success('Lien copié.')
  } catch {
    toast.error('Copie impossible : sélectionnez le lien et copiez-le à la main.')
  }
}

/**
 * Un lien actif : l'URL n'existe qu'à la génération (le jeton n'est pas
 * conservé). Ensuite, pour la retrouver, on régénère — ce qui invalide
 * l'ancienne.
 */
export function PublicLinkActions({ publicUrl, busy, onRegenerate, onRevoke }: PublicLinkActionsProps) {
  return (
    <div className="flex flex-col gap-3">
      {publicUrl ? (
        <div className="flex flex-col gap-2 sm:flex-row">
          <Input readOnly value={publicUrl} aria-label="Lien de suivi public" onFocus={(event) => event.currentTarget.select()} />
          <Button type="button" variant="outline" onClick={() => void copy(publicUrl)}>
            <Copy className="size-4" aria-hidden /> Copier
          </Button>
        </div>
      ) : (
        <p className="text-muted-foreground text-sm">
          Pour des raisons de sécurité, le lien n’est affiché qu’au moment de sa création. Régénérez-le pour l’obtenir à nouveau : l’ancien cessera de fonctionner.
        </p>
      )}
      <div className="flex flex-wrap gap-2">
        <Button type="button" variant="outline" disabled={busy} onClick={onRegenerate}>
          <RefreshCw className="size-4" aria-hidden /> Régénérer
        </Button>
        <Button type="button" variant="destructive" disabled={busy} onClick={onRevoke}>
          <Link2Off className="size-4" aria-hidden /> Révoquer
        </Button>
      </div>
    </div>
  )
}

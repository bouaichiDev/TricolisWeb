import { Link2 } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'

import { usePermissions } from '@/app/auth/useAuth'
import { errorMessage } from '@/shared/api/errors'
import { InlineLoader } from '@/shared/components/feedback/States'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { formatDate, formatDateTime } from '@/shared/utils/format'

import { trackingApi } from '../api/trackingApi'
import { usePublicLinkMutation, usePublicTrackingLink } from '../hooks/usePublicTrackingLink'
import { PublicLinkActions } from './PublicLinkActions'

const STATE_LABELS = { none: 'Aucun lien', active: 'Lien actif', revoked: 'Lien révoqué', expired: 'Lien expiré' } as const

/**
 * Le lien de suivi à envoyer au client final (§38). Visible avec
 * `public_link.view`, géré avec `public_link.manage`.
 */
export function PublicLinkSection({ orderId }: { orderId: string }) {
  const { can } = usePermissions()
  const canView = can('customer.tracking.public_link.view')
  const canManage = can('customer.tracking.public_link.manage')
  const [expiresAt, setExpiresAt] = useState('')
  const link = usePublicTrackingLink(orderId, canView)
  const generate = usePublicLinkMutation(orderId, trackingApi.generateLink)
  const revoke = usePublicLinkMutation(orderId, trackingApi.revokeLink)

  if (!canView) return null

  const run = async (action: () => Promise<unknown>, success: string) => {
    try {
      await action()
      toast.success(success)
    } catch (caught) {
      toast.error(errorMessage(caught))
    }
  }

  const create = () => run(() => generate.mutateAsync({ orderId, expiresAt: expiresAt || undefined }), 'Lien de suivi généré.')
  const revokeLink = () => {
    if (!window.confirm('Révoquer ce lien ? Il cessera immédiatement de fonctionner.')) return
    void run(() => revoke.mutateAsync(orderId), 'Lien révoqué.')
  }

  const state = link.data
  const busy = generate.isPending || revoke.isPending

  return (
    <SectionCard title="Lien de suivi public" description="Un lien à transmettre à votre client final : il suit la livraison sans compte Tricolis.">
      {link.isPending ? (
        <InlineLoader />
      ) : link.isError || !state ? (
        <p className="text-destructive text-sm">{errorMessage(link.error)}</p>
      ) : (
        <div className="flex flex-col gap-4" data-testid="public-link-state" data-state={state.state}>
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <Link2 className="text-muted-foreground size-4" aria-hidden />
            <span className="font-medium">{STATE_LABELS[state.state]}</span>
            {state.exists && <span className="text-muted-foreground">· Expiration : {state.expiresAt ? formatDate(state.expiresAt) : 'jusqu’à révocation'}</span>}
            {state.lastAccessAt && <span className="text-muted-foreground">· Dernière consultation : {formatDateTime(state.lastAccessAt)}</span>}
          </div>

          {state.active && canManage && <PublicLinkActions publicUrl={state.publicUrl} busy={busy} onRegenerate={() => void create()} onRevoke={revokeLink} />}

          {!state.active && canManage && (
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="public-link-expiry">Expiration (facultatif)</Label>
                <Input id="public-link-expiry" type="date" value={expiresAt} onChange={(event) => setExpiresAt(event.target.value)} className="sm:w-48" />
              </div>
              <Button type="button" disabled={busy} onClick={() => void create()}>
                {state.exists ? 'Générer un nouveau lien' : 'Générer un lien'}
              </Button>
            </div>
          )}

          {!canManage && !state.active && <p className="text-muted-foreground text-sm">Demandez à un administrateur de générer le lien.</p>}
        </div>
      )}
    </SectionCard>
  )
}

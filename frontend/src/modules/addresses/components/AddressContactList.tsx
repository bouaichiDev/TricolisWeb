import { Mail, Phone, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { useAddressContacts, useDetachAddressContact } from '../hooks/useEntityAddresses'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { ListSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

/**
 * Contacts d'une adresse.
 *
 * Ils sont rattachés à l'adresse et non à l'entité : qui prévenir dépend du
 * lieu — le magasinier d'un entrepôt n'est pas le comptable du siège. Le rôle
 * est porté par la liaison, un même contact pouvant être livraison ici et
 * facturation ailleurs.
 */
export function AddressContactList({ addressId }: { addressId: string }) {
  const { t } = useTranslation()
  const { data, isPending, error, refetch } = useAddressContacts(addressId)
  const detach = useDetachAddressContact(addressId)

  if (isPending) return <ListSkeleton rows={2} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />

  const links = data ?? []

  if (links.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('addresses.noContact')}</p>
  }

  return (
    <ul className="flex flex-col divide-y rounded-lg border">
      {links.map((link) => (
        <li key={link.id} className="flex items-start justify-between gap-4 p-3">
          <div className="min-w-0">
            <p className="flex flex-wrap items-center gap-2 text-sm font-medium">
              {`${link.contact.firstName} ${link.contact.lastName}`.trim()}
              {link.contactRole ? (
                <Badge variant="secondary" className="font-normal">
                  {t(`contactRoles.${link.contactRole}`, { defaultValue: link.contactRole })}
                </Badge>
              ) : null}
              {link.isPrimary ? (
                <Badge variant="outline" className="font-normal">
                  {t('addresses.primaryContact')}
                </Badge>
              ) : null}
            </p>

            <p className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
              {link.contact.email ? (
                <span className="flex items-center gap-1">
                  <Mail className="size-3" aria-hidden />
                  {link.contact.email}
                </span>
              ) : null}
              {link.contact.phone ?? link.contact.mobile ? (
                <span className="flex items-center gap-1">
                  <Phone className="size-3" aria-hidden />
                  {link.contact.phone ?? link.contact.mobile}
                </span>
              ) : null}
            </p>
          </div>

          <PermissionGuard permission="addresses.update">
            <Button
              variant="ghost"
              size="icon"
              aria-label={t('addresses.detachContact')}
              disabled={detach.isPending}
              onClick={() => detach.mutate(link.id)}
            >
              <Trash2 className="size-4" aria-hidden />
            </Button>
          </PermissionGuard>
        </li>
      ))}
    </ul>
  )
}

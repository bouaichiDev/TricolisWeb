import { RefreshCw } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useStatusOptions } from '@/modules/statuses/hooks/useStatuses'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/shared/components/ui/dropdown-menu'

import { useChangeTourStatus } from '../hooks/useTours'
import type { Tour } from '../types/tour'

/**
 * Faire changer une tournée d'état, depuis sa colonne.
 *
 * **Les passages proposés viennent du référentiel**, pas d'une liste écrite ici :
 * c'est `status_transitions` qui dit ce qu'une tournée peut devenir, et le
 * serveur refuserait tout le reste. Proposer un bouton que le serveur rejette
 * serait une promesse en l'air.
 *
 * Le geste est confirmé : un état ne se reprend pas d'un clic, et « annulée »
 * ferme la tournée pour de bon.
 */
export function TourStatusMenu({ tour }: { tour: Tour }) {
  const { t } = useTranslation()
  const change = useChangeTourStatus()
  const { statuses } = useStatusOptions('tour', tour.status)

  const [target, setTarget] = useState<{ code: string; label: string } | null>(null)

  const others = statuses.filter((status) => status.code !== tour.status)

  if (others.length === 0) return null

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger
          className="rounded p-1 text-muted-foreground transition-colors hover:text-primary"
          title={t('tours.changeStatus')}
          aria-label={t('tours.changeStatus')}
        >
          <RefreshCw className="size-4" aria-hidden />
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end">
          {others.map((status) => (
            <DropdownMenuItem
              key={status.id}
              onSelect={() => setTarget({ code: status.code, label: status.label })}
            >
              {status.label}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>

      <ConfirmDialog
        open={target !== null}
        onOpenChange={(open) => (open ? undefined : setTarget(null))}
        title={t('tours.changeStatusTitle')}
        description={t('tours.changeStatusBody', {
          number: tour.tourNumber,
          status: target?.label ?? '',
        })}
        confirmLabel={t('tours.changeStatus')}
        isPending={change.isPending}
        onConfirm={() => {
          if (target !== null) change.mutate({ id: tour.id, status: target.code })

          setTarget(null)
        }}
      />
    </>
  )
}

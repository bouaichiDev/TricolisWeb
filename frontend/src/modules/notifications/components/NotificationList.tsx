import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import type { AppNotification } from '../api/notifications.api'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { formatDateTime } from '@/shared/utils/format'
import { cn } from '@/shared/utils/cn'

interface NotificationListProps {
  notifications: AppNotification[]
  /** Les internes portent un état de lecture ; les externes n'en ont pas. */
  showsReadState: boolean
  emptyLabel: string
  onOpen: (notification: AppNotification) => void
}

/**
 * Une moitié du panneau — interne ou externe.
 *
 * Chaque ligne est un **bouton**, pas un lien : ouvrir une notification la
 * marque lue au passage, ce qu'une ancre ne peut pas faire sans que la
 * navigation parte avant l'écriture. Le clavier y arrive donc comme la souris,
 * ce qu'un `div` cliquable n'aurait pas donné.
 *
 * Une notification non lue porte une **pastille**, pas seulement une graisse
 * différente : « en gras » se confond avec un titre long, et disparaît pour qui
 * règle son navigateur en gras partout.
 *
 * Le canal n'est pas affiché en toutes lettres : dans la moitié interne il vaut
 * toujours la même chose, et dans l'externe le statut — « échec » — porte
 * l'information qui appelle un geste.
 */
export function NotificationList({
  notifications,
  showsReadState,
  emptyLabel,
  onOpen,
}: NotificationListProps) {
  const { t } = useTranslation()
  const navigate = useNavigate()

  if (notifications.length === 0) {
    return <p className="px-4 py-6 text-center text-sm text-muted-foreground">{emptyLabel}</p>
  }

  return (
    <ul className="flex max-h-80 flex-col divide-y overflow-y-auto">
      {notifications.map((notification) => (
        <li key={notification.id}>
          <button
            type="button"
            className="flex w-full items-start gap-2.5 px-4 py-3 text-left transition-colors hover:bg-accent/60"
            onClick={() => {
              onOpen(notification)
              if (notification.route !== null) void navigate(notification.route)
            }}
          >
            {showsReadState ? (
              <span
                aria-hidden
                className={cn(
                  'mt-1.5 size-2 shrink-0 rounded-full',
                  notification.isRead ? 'bg-transparent' : 'bg-primary',
                )}
              />
            ) : null}

            <span className="flex min-w-0 flex-1 flex-col gap-0.5">
              <span
                className={cn(
                  'truncate text-sm',
                  showsReadState && !notification.isRead && 'font-medium',
                )}
              >
                {notification.title ?? t('notifications.untitled')}
              </span>
              <span className="text-xs text-muted-foreground">
                {formatDateTime(notification.date)}
              </span>
            </span>

            {notification.status !== null && !showsReadState ? (
              <StatusBadge status={notification.status} source="order_communication" />
            ) : null}
          </button>
        </li>
      ))}
    </ul>
  )
}

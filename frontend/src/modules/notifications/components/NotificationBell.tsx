import { Bell } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { NotificationList } from './NotificationList'
import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotifications,
} from '../hooks/useNotifications'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { Button } from '@/shared/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/shared/components/ui/popover'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

/**
 * La cloche du bandeau supérieur.
 *
 * Elle est là pour **tout le monde** — administrateur de plateforme comme membre
 * d'un organisme — et ne se cache jamais : une cloche qui apparaît et disparaît
 * selon les droits ferait douter de ce qu'on a manqué. Ce qu'elle contient, en
 * revanche, dépend de qui regarde.
 *
 * **Deux onglets, parce que le domaine distingue déjà deux choses :**
 *
 * - **Internes** — ce qui m'est adressé. Elles portent un état de lecture qui
 *   n'appartient qu'à moi, et ce sont elles seules que compte la pastille ;
 * - **Externes** — les envois de l'organisation à ses clients **qui ont
 *   échoué**. Ils ne sont adressés à personne ici : l'organisation entière les
 *   reprend, et leur donner un état de lecture par utilisateur laisserait croire
 *   qu'un collègue s'en occupe.
 *
 * Un envoi réussi n'y figure pas. Il n'appelle aucune action, et noierait les
 * échecs qui, eux, en appellent une — l'historique complet est à un clic.
 *
 * La pastille ne montre pas de nombre au-delà de neuf : « 47 » dans un rond de
 * seize pixels se lit mal, et la différence entre quarante-sept et cinquante ne
 * change rien au geste.
 */
export function NotificationBell() {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  const { data } = useNotifications()
  const markRead = useMarkNotificationRead()
  const markAllRead = useMarkAllNotificationsRead()

  const unread = data?.unread ?? 0

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="relative"
          aria-label={
            unread === 0 ? t('notifications.title') : t('notifications.unread', { count: unread })
          }
        >
          <Bell className="size-5" aria-hidden />
          {unread > 0 ? (
            <span className="absolute right-1 top-1 flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-medium leading-4 text-primary-foreground">
              {unread > 9 ? '9+' : unread}
            </span>
          ) : null}
        </Button>
      </PopoverTrigger>

      <PopoverContent align="end" className="w-96 p-0">
        <div className="flex items-center justify-between gap-2 border-b px-4 py-3">
          <span className="text-sm font-medium">{t('notifications.title')}</span>

          {unread > 0 ? (
            <Button
              variant="ghost"
              size="sm"
              disabled={markAllRead.isPending}
              onClick={() => markAllRead.mutate(undefined)}
            >
              {t('notifications.markAllRead')}
            </Button>
          ) : null}
        </div>

        <Tabs defaultValue="internal">
          <TabsList className="mx-4 mt-3">
            <TabsTrigger value="internal">{t('notifications.internal')}</TabsTrigger>
            <TabsTrigger value="external">{t('notifications.external')}</TabsTrigger>
          </TabsList>

          <TabsContent value="internal" className="mt-3">
            <NotificationList
              notifications={data?.internal ?? []}
              showsReadState
              emptyLabel={t('notifications.emptyInternal')}
              onOpen={(notification) => {
                setOpen(false)
                if (!notification.isRead) markRead.mutate(notification.id)
              }}
            />
          </TabsContent>

          <TabsContent value="external" className="mt-3">
            <NotificationList
              notifications={data?.external ?? []}
              showsReadState={false}
              emptyLabel={t('notifications.emptyExternal')}
              onOpen={() => setOpen(false)}
            />

            {/* Le panneau montre les dix derniers échecs ; l'historique les
                montre tous, et sait filtrer. Le lien n'est proposé qu'à qui
                peut l'ouvrir. */}
            <PermissionGuard permission="order_communications.view">
              <div className="border-t px-4 py-2">
                <Link
                  to="/communications/history"
                  onClick={() => setOpen(false)}
                  className="text-sm font-medium text-primary hover:underline"
                >
                  {t('notifications.seeHistory')}
                </Link>
              </div>
            </PermissionGuard>
          </TabsContent>
        </Tabs>
      </PopoverContent>
    </Popover>
  )
}

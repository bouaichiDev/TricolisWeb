import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it, vi } from 'vitest'

import { NotificationBell } from './NotificationBell'
import type { AppNotification, NotificationFeed } from '../api/notifications.api'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

/**
 * La cloche du bandeau.
 *
 * Elle porte deux moitiés que le domaine distingue déjà, et qui ne se
 * ressemblent pas : les internes m'appartiennent — état de lecture, pastille —,
 * les externes appartiennent à l'organisation et n'en ont pas.
 */
function notification(overrides: Partial<AppNotification> = {}): AppNotification {
  return {
    id: 'n1',
    title: 'Une commande vous attend',
    recipient: 'Admin Tricolis',
    channel: 'internal_notification',
    status: 'sent',
    isRead: false,
    date: '2026-09-05T09:00:00+00:00',
    route: '/orders/o1',
    ...overrides,
  }
}

function feedHandler(feed: Partial<NotificationFeed>) {
  return http.get(`${API}/notifications`, () =>
    HttpResponse.json({
      data: { unread: 0, internal: [], external: [], ...feed },
      meta: [],
    }),
  )
}

function render(feed: Partial<NotificationFeed>, permissions = ['order_communications.view']) {
  server.use(feedHandler(feed))
  // Le référentiel des statuts nomme les envois en échec de la moitié externe.
  server.use(http.get(`${API}/statuses`, () => HttpResponse.json({ data: [], meta: {} })))

  return renderWithProviders(<NotificationBell />, { membership: withPermissions(permissions) })
}

describe('cloche des notifications', () => {
  it('reste discrète quand il n’y a rien à signaler', async () => {
    render({})

    const bell = await screen.findByRole('button', { name: 'Notifications' })

    expect(bell).toBeInTheDocument()
    expect(screen.queryByText('9+')).not.toBeInTheDocument()
  })

  it('compte les internes non lues, et elles seules', async () => {
    render({
      unread: 3,
      internal: [notification()],
      external: [notification({ id: 'x1', channel: 'email', status: 'failed' })],
    })

    expect(await screen.findByText('3')).toBeInTheDocument()
  })

  /**
   * « 47 » dans un rond de seize pixels se lit mal, et la différence entre
   * quarante-sept et cinquante ne change rien au geste.
   */
  it('n’écrit pas un nombre qu’on ne lirait pas', async () => {
    render({ unread: 47, internal: [notification()] })

    expect(await screen.findByText('9+')).toBeInTheDocument()
  })

  it('sépare ce qui m’est adressé de ce que l’organisation a envoyé', async () => {
    const user = userEvent.setup()

    render({
      unread: 1,
      internal: [notification()],
      external: [notification({ id: 'x1', title: 'client@exemple.test', channel: 'email' })],
    })

    await user.click(await screen.findByRole('button', { name: /1 notification/ }))

    expect(await screen.findByText('Une commande vous attend')).toBeInTheDocument()
    expect(screen.queryByText('client@exemple.test')).not.toBeInTheDocument()

    await user.click(screen.getByRole('tab', { name: 'Externes' }))

    expect(await screen.findByText('client@exemple.test')).toBeInTheDocument()
  })

  it('marque une notification lue en l’ouvrant', async () => {
    const user = userEvent.setup()
    const read = vi.fn()

    render({ unread: 1, internal: [notification()] })

    server.use(
      http.post(`${API}/notifications/n1/read`, () => {
        read()

        return HttpResponse.json({ data: { unread: 0 }, meta: [] })
      }),
    )

    await user.click(await screen.findByRole('button', { name: /1 notification/ }))
    await user.click(await screen.findByText('Une commande vous attend'))

    await waitFor(() => expect(read).toHaveBeenCalled())
  })

  it('ne propose « tout marquer comme lu » que s’il reste quelque chose à lire', async () => {
    const user = userEvent.setup()

    render({ unread: 0, internal: [notification({ isRead: true })] })

    await user.click(await screen.findByRole('button', { name: 'Notifications' }))

    expect(screen.queryByRole('button', { name: 'Tout marquer comme lu' })).not.toBeInTheDocument()
  })

  /**
   * L'historique complet n'est proposé qu'à qui peut l'ouvrir : sans la
   * permission, le lien mènerait à un écran qui refuse.
   */
  it('ne propose l’historique qu’avec la permission', async () => {
    const user = userEvent.setup()

    render({ external: [notification({ id: 'x1', channel: 'email' })] }, [])

    await user.click(await screen.findByRole('button', { name: 'Notifications' }))
    await user.click(screen.getByRole('tab', { name: 'Externes' }))

    expect(
      screen.queryByRole('link', { name: "Voir l'historique des communications" }),
    ).not.toBeInTheDocument()
  })
})

import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'

import { CATALOGUE, captureSave, menuHandler, renderPanel, type SentItem } from './menuSettingsTestData'
import { server } from '@/test/server'

/**
 * Réglage du menu par l'organisation : l'apparence des entrées.
 *
 * Rang, nom, icône, groupe. Ces quatre-là se vérifient dans **ce qui est
 * envoyé** plutôt que dans l'écran : une entrée promue et une entrée restée en
 * place s'y ressemblent, alors que la charge utile les distingue sans
 * ambiguïté.
 *
 * Ce que l'organisation ne choisit pas reste la destination : route et
 * permission appartiennent au catalogue, en code.
 */
describe('personnalisation du menu', () => {
  /**
   * Une entrée se déplace parmi ses frères, et les rangs partent tous
   * ensemble : ils n'ont de sens que les uns par rapport aux autres.
   */
  it('réordonne une entrée et renumérote la liste', async () => {
    const sent: SentItem[][] = []
    server.use(menuHandler(CATALOGUE), captureSave(sent))
    renderPanel()

    await screen.findByText('Clients')
    await userEvent.click(screen.getByRole('button', { name: 'Descendre « Clients »' }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(sent).toHaveLength(1)
    })

    const order = sent[0].map((item) => item.code)
    expect(order.indexOf('customers')).toBeGreaterThan(order.indexOf('resources'))
    // Le groupe emmène ses entrées : « Agences » reste sous « Ressources ».
    expect(order).toEqual(['resources', 'agencies', 'depots', 'customers', 'administration'])
    expect(sent[0].map((item) => item.position)).toEqual([0, 1, 2, 3, 4])
  })

  /** Un enfant ne quitte pas son groupe : le premier ne peut pas monter. */
  it('ne fait pas sortir un enfant de son groupe', async () => {
    server.use(menuHandler(CATALOGUE))
    renderPanel()

    await screen.findByText('Clients')

    expect(screen.getByRole('button', { name: 'Monter « Agences »' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Descendre « Dépôts »' })).toBeDisabled()
  })

  it('renomme une entrée et change son icône', async () => {
    const sent: SentItem[][] = []
    server.use(menuHandler(CATALOGUE), captureSave(sent))
    renderPanel()

    await screen.findByText('Clients')
    await userEvent.click(screen.getByRole('button', { name: 'Personnaliser « Clients »' }))

    const field = screen.getByLabelText('Nom affiché')
    await userEvent.clear(field)
    await userEvent.type(field, 'Donneurs d’ordre')
    await userEvent.click(screen.getByRole('radio', { name: 'Truck' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmer' }))

    // Le nom choisi remplace celui du catalogue dans la liste même : c'est le
    // seul moyen de relire son propre réglage avant d'enregistrer.
    expect(await screen.findByText('Donneurs d’ordre')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(sent).toHaveLength(1)
    })

    const customers = sent[0].find((item) => item.code === 'customers')
    expect(customers?.label).toBe('Donneurs d’ordre')
    expect(customers?.icon).toBe('Truck')
  })

  /**
   * Le serveur renvoie l'icône effective sans dire d'où elle vient : la
   * réécrire figerait en base une icône que personne n'a choisie.
   */
  it('n’envoie pas l’icône des entrées auxquelles on n’a pas touché', async () => {
    const sent: SentItem[][] = []
    server.use(menuHandler(CATALOGUE), captureSave(sent))
    renderPanel()

    await screen.findByText('Clients')
    await userEvent.click(screen.getByRole('switch', { name: 'Clients' }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(sent).toHaveLength(1)
    })

    expect(sent[0].every((item) => item.icon === undefined)).toBe(true)
  })

  /** Vider le champ est le geste qui revient au libellé livré. */
  it('revient au libellé du catalogue quand le champ est vidé', async () => {
    const sent: SentItem[][] = []
    const renamed = CATALOGUE.map((item) =>
      item.code === 'customers' ? { ...item, label: 'Donneurs d’ordre' } : item,
    )
    server.use(menuHandler(renamed), captureSave(sent))
    renderPanel()

    await screen.findByText('Donneurs d’ordre')
    await userEvent.click(
      screen.getByRole('button', { name: 'Personnaliser « Donneurs d’ordre »' }),
    )
    await userEvent.clear(screen.getByLabelText('Nom affiché'))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmer' }))

    expect(await screen.findByText('Clients')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(sent).toHaveLength(1)
    })

    expect(sent[0].find((item) => item.code === 'customers')?.label).toBeNull()
  })

  /**
   * Sortir une entrée d'un sous-menu pour en faire un menu : c'est le même
   * geste que l'inverse, changer le groupe d'accueil.
   */
  it('sort une entrée de son groupe pour la mettre au premier niveau', async () => {
    const sent: SentItem[][] = []
    server.use(menuHandler(CATALOGUE), captureSave(sent))
    renderPanel()

    await screen.findByText('Clients')
    await userEvent.click(screen.getByRole('button', { name: 'Personnaliser « Agences »' }))
    await userEvent.click(screen.getByRole('combobox'))
    await userEvent.click(await screen.findByRole('option', { name: 'Menu principal' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmer' }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(sent).toHaveLength(1)
    })

    expect(sent[0].find((item) => item.code === 'agencies')?.parent).toBeNull()
  })

  it('range une entrée du premier niveau dans un groupe', async () => {
    const sent: SentItem[][] = []
    server.use(menuHandler(CATALOGUE), captureSave(sent))
    renderPanel()

    await screen.findByText('Clients')
    await userEvent.click(screen.getByRole('button', { name: 'Personnaliser « Clients »' }))
    await userEvent.click(screen.getByRole('combobox'))
    await userEvent.click(await screen.findByRole('option', { name: 'Ressources' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmer' }))
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(sent).toHaveLength(1)
    })

    const order = sent[0].map((item) => item.code)

    expect(sent[0].find((item) => item.code === 'customers')?.parent).toBe('resources')
    // L'entrée rejoint le bloc de son groupe : l'ordre envoyé doit la placer
    // parmi ses nouveaux frères, sinon la barre latérale et cet écran ne
    // montreraient pas la même chose.
    expect(order.indexOf('customers')).toBeGreaterThan(order.indexOf('resources'))
    expect(order.indexOf('customers')).toBeLessThan(order.indexOf('administration'))
  })

  /**
   * Un groupe n'a pas de niveau où descendre : la barre latérale en rend deux,
   * et ses entrées se retrouveraient au troisième, où rien ne les affiche.
   */
  it('ne propose pas de déplacer un groupe', async () => {
    server.use(menuHandler(CATALOGUE))
    renderPanel()

    await screen.findByText('Clients')
    await userEvent.click(screen.getByRole('button', { name: 'Personnaliser « Ressources »' }))

    expect(await screen.findByLabelText('Nom affiché')).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })
})

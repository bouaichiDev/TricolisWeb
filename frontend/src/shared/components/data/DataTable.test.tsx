import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

import { DataTable, type Column } from './DataTable'
import { renderWithProviders } from '@/test/renderWithProviders'

interface Row {
  id: string
  name: string
  city: string
}

const columns: Column<Row>[] = [
  { key: 'name', header: 'Nom', sortKey: 'name', cell: (row) => row.name },
  { key: 'city', header: 'Ville', cell: (row) => row.city },
]

const rows: Row[] = [
  { id: '1', name: 'Client A', city: 'Rabat' },
  { id: '2', name: 'Client B', city: 'Casablanca' },
]

function meta(overrides: Partial<Record<string, number>> = {}) {
  return { currentPage: 1, perPage: 25, total: 2, lastPage: 1, ...overrides }
}

describe('DataTable', () => {
  it('affiche les lignes fournies', () => {
    renderWithProviders(<DataTable columns={columns} rows={rows} rowKey={(row) => row.id} />)

    expect(screen.getByText('Client A')).toBeInTheDocument()
    expect(screen.getByText('Casablanca')).toBeInTheDocument()
  })

  it('affiche l’état vide plutôt qu’une table sans lignes', () => {
    renderWithProviders(
      <DataTable columns={columns} rows={[]} rowKey={(row) => row.id} emptyMessage="Aucun client" />,
    )

    expect(screen.getByText('Aucun client')).toBeInTheDocument()
  })

  it('affiche l’erreur et propose de réessayer', async () => {
    const onRetry = vi.fn()
    renderWithProviders(
      <DataTable
        columns={columns}
        rows={[]}
        rowKey={(row) => row.id}
        error={new Error('Service indisponible')}
        onRetry={onRetry}
      />,
    )

    expect(screen.getByText('Service indisponible')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Réessayer' }))
    expect(onRetry).toHaveBeenCalledOnce()
  })

  /**
   * Le tri est délégué au serveur : la table ne réordonne rien elle-même, elle
   * remonte la colonne demandée. Réordonner les 25 lignes de la page courante
   * donnerait un ordre faux sur l'ensemble.
   */
  it('remonte la colonne cliquée sans réordonner localement', async () => {
    const onSortChange = vi.fn()
    renderWithProviders(
      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(row) => row.id}
        onSortChange={onSortChange}
      />,
    )

    await userEvent.click(screen.getByRole('button', { name: /trier/i }))

    expect(onSortChange).toHaveBeenCalledWith('name')
    const cells = screen.getAllByRole('cell').map((cell) => cell.textContent)
    expect(cells[0]).toBe('Client A')
  })

  it('ne rend pas de bouton de tri sur une colonne sans sortKey', () => {
    renderWithProviders(
      <DataTable columns={columns} rows={rows} rowKey={(row) => row.id} onSortChange={vi.fn()} />,
    )

    expect(screen.getAllByRole('button', { name: /trier/i })).toHaveLength(1)
  })

  describe('pagination', () => {
    it('désactive « précédent » sur la première page', () => {
      renderWithProviders(
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(row) => row.id}
          meta={meta({ lastPage: 3, total: 60 })}
          onPageChange={vi.fn()}
        />,
      )

      expect(screen.getByRole('button', { name: 'Précédent' })).toBeDisabled()
      expect(screen.getByRole('button', { name: 'Suivant' })).toBeEnabled()
    })

    it('demande la page suivante', async () => {
      const onPageChange = vi.fn()
      renderWithProviders(
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(row) => row.id}
          meta={meta({ currentPage: 2, lastPage: 3, total: 60 })}
          onPageChange={onPageChange}
        />,
      )

      await userEvent.click(screen.getByRole('button', { name: 'Suivant' }))
      expect(onPageChange).toHaveBeenCalledWith(3)
    })

    it('désactive « suivant » sur la dernière page', () => {
      renderWithProviders(
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(row) => row.id}
          meta={meta({ currentPage: 3, lastPage: 3, total: 60 })}
          onPageChange={vi.fn()}
        />,
      )

      expect(screen.getByRole('button', { name: 'Suivant' })).toBeDisabled()
    })

    it('n’affiche aucune pagination quand la liste est vide', () => {
      renderWithProviders(
        <DataTable
          columns={columns}
          rows={[]}
          rowKey={(row) => row.id}
          meta={meta({ total: 0 })}
          onPageChange={vi.fn()}
        />,
      )

      expect(screen.queryByRole('button', { name: 'Suivant' })).not.toBeInTheDocument()
    })
  })

  /**
   * Responsive : les colonnes secondaires portent `hidden md:table-cell`. Sous
   * jsdom, aucune requête média ne s'applique — on vérifie donc la classe, qui
   * est ce qui produit réellement le comportement.
   */
  it('marque les colonnes secondaires comme masquées sur petit écran', () => {
    const responsive: Column<Row>[] = [
      columns[0],
      { ...columns[1], hideOnMobile: true },
    ]

    renderWithProviders(
      <DataTable columns={responsive} rows={rows} rowKey={(row) => row.id} />,
    )

    expect(screen.getByRole('columnheader', { name: 'Ville' })).toHaveClass('hidden', 'md:table-cell')
  })
})

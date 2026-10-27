import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

import { DataTable, type Column } from './DataTable'

interface Row {
  id: string
  code: string
}

const rows: Row[] = [{ id: '1', code: 'BILLY-80' }]

/** Le tableau du back-office : tri serveur annoncé, filtres sous les en-têtes, actions en dernier. */
describe('DataTable', () => {
  it('puts each column filter on a second header row, under its column', () => {
    const columns: Column<Row>[] = [
      { key: 'code', header: 'Code', cell: (row) => row.code, filter: <input aria-label="Filtrer le code" /> },
      { key: 'other', header: 'Autre', cell: () => '—' },
    ]
    render(<DataTable columns={columns} rows={rows} rowKey={(row) => row.id} />)

    const headerRows = within(screen.getByRole('table')).getAllByRole('row').slice(0, 2)
    expect(within(headerRows[1]).getByLabelText('Filtrer le code')).toBeInTheDocument()
    expect(within(headerRows[0]).queryByLabelText('Filtrer le code')).not.toBeInTheDocument()
  })

  it('adds no filter row when no column has a filter', () => {
    render(<DataTable columns={[{ key: 'code', header: 'Code', cell: (row: Row) => row.code }]} rows={rows} rowKey={(row) => row.id} />)

    expect(within(screen.getByRole('table')).getAllByRole('row')).toHaveLength(2)
  })

  it('announces the sort it will ask the server for', async () => {
    const onSortChange = vi.fn()
    render(
      <DataTable columns={[{ key: 'code', header: 'Code', sortKey: 'articleCode', cell: (row: Row) => row.code }]} rows={rows} rowKey={(row) => row.id} sort="articleCode" direction="asc" onSortChange={onSortChange} />,
    )

    await userEvent.setup().click(screen.getByRole('button', { name: 'Trier par ordre décroissant' }))

    expect(onSortChange).toHaveBeenCalledWith('articleCode')
  })

  it('keeps the actions column last, with its header', () => {
    render(<DataTable columns={[{ key: 'code', header: 'Code', cell: (row: Row) => row.code }]} rows={rows} rowKey={(row) => row.id} actions={() => <button type="button">Voir</button>} />)

    const headers = within(screen.getByRole('table')).getAllByRole('columnheader')
    expect(headers.at(-1)).toHaveTextContent('Actions')
  })
})

import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { BillableServicePicker } from '../components/BillableServicePicker'
import {
  EMPTY_BILLABLE_FILTERS,
  type BillableColumnFilters,
} from '../components/billableFilters'
import { InvoiceHeaderFields, type InvoiceHeaderState } from '../components/InvoiceHeaderFields'
import { linesFromServices, previewTotal } from '../components/invoiceDraft'
import { useCreateInvoice } from '../hooks/useInvoices'
import type { BillableService } from '../types/invoice'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { useApiMessage } from '@/shared/hooks/useApiMessage'
import { formatMoney } from '@/shared/utils/format'

const TODAY = new Date().toISOString().slice(0, 10)

/**
 * Composer une facture à partir de prestations réalisées.
 *
 * **Le client se choisit d'abord, et se fige ensuite.** Les prestations
 * facturables ne se demandent que sous un client (§112), et une facture ne
 * porte qu'un client : changer d'avis en cours de sélection produirait un
 * document mêlant deux destinataires. Le champ se verrouille dès la première
 * prestation retenue, et le dit.
 *
 * **La devise vient du travail, pas d'un réglage par défaut.** Elle est portée
 * par la commande ; la première prestation retenue l'impose à la facture, et une
 * prestation d'une autre devise est refusée — un document à deux monnaies
 * n'aurait pas de total.
 *
 * Le brouillon n'est **pas** enregistré au fil de l'eau : la facture naît en une
 * fois, avec ses lignes, parce que le serveur exige au moins une ligne (§8) —
 * une facture vide n'aurait rien à clôturer.
 */
export function InvoiceCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [header, setHeader] = useState<InvoiceHeaderState>({
    customerId: '',
    invoiceNumber: '',
    invoiceDate: TODAY,
    currencyCode: 'MAD',
    externalReference: '',
    remark: '',
  })
  const [filters, setFilters] = useState<BillableColumnFilters>(EMPTY_BILLABLE_FILTERS)
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<Map<string, BillableService>>(new Map())

  const create = useCreateInvoice()
  const failure = useApiMessage(create.error)
  const chosen = [...selected.values()]

  const [mismatch, setMismatch] = useState<string | null>(null)

  const toggle = (service: BillableService) => {
    setMismatch(null)

    if (selected.has(service.id)) {
      setSelected((current) => {
        const next = new Map(current)
        next.delete(service.id)

        return next
      })

      return
    }

    const currency = service.currencyCode ?? header.currencyCode

    // Une facture n'a qu'une devise : melanger deux monnaies ne donnerait
    // aucun total. La premiere prestation retenue impose la sienne.
    if (chosen.length > 0 && currency !== header.currencyCode) {
      setMismatch(t('billing.invoices.currencyMismatch', { currency }))

      return
    }

    setHeader((current) => ({ ...current, currencyCode: currency }))
    setSelected((current) => new Map(current).set(service.id, service))
  }

  const ready =
    header.customerId !== '' &&
    header.invoiceNumber.trim() !== '' &&
    header.invoiceDate !== '' &&
    header.currencyCode.length === 3 &&
    chosen.length > 0

  const submit = () => {
    create.mutate(
      {
        customerId: header.customerId,
        invoiceNumber: header.invoiceNumber.trim(),
        invoiceDate: header.invoiceDate,
        periodFrom: filters.periodFrom || null,
        periodTo: filters.periodTo || null,
        currencyCode: header.currencyCode,
        externalReference: header.externalReference || null,
        remark: header.remark || null,
        // Une facture nait au brouillon : c'est le seul etat depuis lequel on
        // peut encore corriger ses lignes.
        status: 'draft',
        lines: linesFromServices(chosen),
      },
      { onSuccess: (invoice) => void navigate(`/billing/invoices/${invoice.id}`) },
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('billing.invoices.createTitle')}
        description={t('billing.invoices.createSubtitle')}
      />

      <FormErrorSummary message={failure ?? mismatch} />

      <SectionCard title={t('billing.invoices.sections.header')}>
        <InvoiceHeaderFields
          value={header}
          onChange={(next) => {
            // Changer de client viderait la selection sans le dire : le champ
            // est verrouille des la premiere prestation retenue.
            setHeader(next)
            setPage(1)
          }}
          customerLocked={chosen.length > 0}
        />
      </SectionCard>

      <SectionCard
        title={t('billing.invoices.sections.services')}
        description={t('billing.invoices.sections.servicesHint')}
      >
        <BillableServicePicker
          customerId={header.customerId}
          currencyCode={header.currencyCode}
          filters={filters}
          onFiltersChange={(patch) => {
            // Toute page au-dela de la premiere n'a plus de sens des que le
            // filtre change : le resultat n'a plus la meme taille.
            setFilters((current) => ({ ...current, ...patch }))
            setPage(1)
          }}
          onFiltersReset={() => {
            setFilters(EMPTY_BILLABLE_FILTERS)
            setPage(1)
          }}
          page={page}
          onPageChange={setPage}
          selected={selected}
          onToggle={toggle}
        />
      </SectionCard>

      <div className="flex flex-col gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-sm">
          {t('billing.invoices.selectedCount', { count: chosen.length })}
          {chosen.length > 0 ? (
            <span className="ml-2 font-medium tabular-nums">
              {formatMoney(previewTotal(chosen), header.currencyCode)}
            </span>
          ) : null}
        </p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => void navigate('/billing/invoices')}>
            {t('common.cancel')}
          </Button>
          <Button disabled={!ready || create.isPending} onClick={submit}>
            {t('billing.invoices.createAction')}
          </Button>
        </div>
      </div>
    </div>
  )
}

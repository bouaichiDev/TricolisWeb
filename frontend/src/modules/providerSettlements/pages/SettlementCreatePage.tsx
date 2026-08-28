import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router-dom'

import {
  SettleableServicePicker,
  type SettleablePeriod,
} from '../components/SettleableServicePicker'
import { linesFromServices, previewTotal } from '../components/settlementDraft'
import { useCreateSettlement } from '../hooks/useSettlements'
import type { SettleableService } from '../types/settlement'
import { useProviderList } from '@/modules/providers/hooks/useProviders'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { formatMoney } from '@/shared/utils/format'

/**
 * Composer le décompte d'un fournisseur.
 *
 * **Le fournisseur se choisit d'abord, et se fige ensuite.** Les prestations à
 * régler ne se demandent que sous un fournisseur, et un décompte n'en paie
 * qu'un : en changer après avoir retenu des prestations produirait un document
 * réglant deux transporteurs à la fois.
 *
 * Le §101 veut qu'on parte du fournisseur : un lien depuis sa fiche préremplit
 * le champ par `?provider=`, et l'écran reste atteignable seul depuis le menu,
 * où le fournisseur se choisit.
 *
 * La TVA se saisit au niveau du décompte, pas de la ligne : le diagramme ne
 * porte un taux que sur les lignes de facture, et l'inventer ici obligerait à
 * choisir un taux pour le fournisseur à sa place.
 */
export function SettlementCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()

  const [params] = useSearchParams()
  const [providerId, setProviderId] = useState(params.get('provider') ?? '')
  const [settlementNumber, setSettlementNumber] = useState('')
  const [taxTotal, setTaxTotal] = useState('0')
  const [period, setPeriod] = useState<SettleablePeriod>({ from: '', to: '' })
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<Map<string, SettleableService>>(new Map())

  const providers = useProviderList({ page: 1, perPage: 100 })
  const create = useCreateSettlement(providerId)
  const chosen = [...selected.values()]

  const toggle = (service: SettleableService) => {
    setSelected((current) => {
      const next = new Map(current)
      if (next.has(service.id)) next.delete(service.id)
      else next.set(service.id, service)

      return next
    })
  }

  const ready = providerId !== '' && settlementNumber.trim() !== '' && chosen.length > 0

  const submit = () => {
    create.mutate(
      {
        providerId,
        settlementNumber: settlementNumber.trim(),
        periodFrom: period.from || null,
        periodTo: period.to || null,
        taxTotal: Number.parseFloat(taxTotal) || 0,
        // Un decompte nait au brouillon : c'est le seul etat ou ses lignes se
        // corrigent encore.
        status: 'draft',
        lines: linesFromServices(chosen),
      },
      { onSuccess: (settlement) => void navigate(`/billing/settlements/${settlement.id}`) },
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('settlements.createTitle')}
        description={t('settlements.createSubtitle')}
      />

      <SectionCard title={t('settlements.sections.header')}>
        <div className="grid gap-4 sm:grid-cols-3">
          <AsyncSelect
            label={t('settlements.fields.provider')}
            value={providerId}
            onChange={setProviderId}
            options={(providers.data?.data ?? []).map((provider) => ({
              value: provider.id,
              label: provider.name,
              hint: provider.code,
            }))}
            isLoading={providers.isPending}
            disabled={chosen.length > 0}
            description={chosen.length > 0 ? t('settlements.providerLocked') : undefined}
          />

          <div className="flex flex-col gap-2">
            <Label htmlFor="settlement-number">{t('settlements.fields.settlementNumber')}</Label>
            <Input
              id="settlement-number"
              value={settlementNumber}
              onChange={(event) => setSettlementNumber(event.target.value)}
              required
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="settlement-tax">{t('settlements.fields.taxTotal')}</Label>
            <Input
              id="settlement-tax"
              type="number"
              min={0}
              step="0.01"
              value={taxTotal}
              onChange={(event) => setTaxTotal(event.target.value)}
            />
          </div>
        </div>
      </SectionCard>

      <SectionCard
        title={t('settlements.sections.services')}
        description={t('settlements.sections.servicesHint')}
      >
        <SettleableServicePicker
          providerId={providerId}
          period={period}
          onPeriodChange={(next) => {
            setPeriod(next)
            setPage(1)
          }}
          search={search}
          onSearchChange={(next) => {
            setSearch(next)
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
          {t('settlements.selectedCount', { count: chosen.length })}
          {chosen.length > 0 ? (
            <span className="ml-2 font-medium tabular-nums">
              {formatMoney(previewTotal(chosen))}
            </span>
          ) : null}
        </p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => void navigate('/billing/settlements')}>
            {t('common.cancel')}
          </Button>
          <Button disabled={!ready || create.isPending} onClick={submit}>
            {t('settlements.createAction')}
          </Button>
        </div>
      </div>
    </div>
  )
}

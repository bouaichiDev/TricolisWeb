import { useTranslation } from 'react-i18next'

import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

import { StatusDefaultsPanel } from '../components/StatusDefaultsPanel'
import { StatusPropagationsPanel } from '../components/StatusPropagationsPanel'
import { StatusReferentialPanel } from '../components/StatusReferentialPanel'

/**
 * Les statuts, en trois onglets.
 *
 * Le **référentiel** nomme les codes ; les deux autres règlent ce que ces
 * statuts font : celui qu'une entité reçoit en naissant, et ce que chacun
 * entraîne sur ses voisins.
 *
 * Ces réglages tenaient dans des fenêtres modales, et c'était trop petit : la
 * liste des entités en compte près de quarante, et une règle de propagation se
 * lit sur toute une ligne. Un onglet donne la largeur de la page, et laisse la
 * place aux boutons de modification.
 */
export function StatusListPage() {
  const { t } = useTranslation()

  const tabs = [
    { value: 'referential', label: t('statuses.tabs.referential'), panel: <StatusReferentialPanel /> },
    { value: 'defaults', label: t('statuses.defaults.title'), panel: <StatusDefaultsPanel /> },
    { value: 'propagations', label: t('statuses.propagations.title'), panel: <StatusPropagationsPanel /> },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('statuses.title')} description={t('statuses.subtitle')} />

      <Tabs defaultValue="referential" className="flex flex-col gap-4">
        <TabsList className="w-full justify-start overflow-x-auto">
          {tabs.map((tab) => (
            <TabsTrigger key={tab.value} value={tab.value}>
              {tab.label}
            </TabsTrigger>
          ))}
        </TabsList>

        {tabs.map((tab) => (
          <TabsContent key={tab.value} value={tab.value}>
            {tab.panel}
          </TabsContent>
        ))}
      </Tabs>
    </div>
  )
}

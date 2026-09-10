import type { TFunction } from 'i18next'

import type { Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { Badge } from '@/shared/components/ui/badge'

import type { Template } from '../types/template'

/**
 * Les colonnes de la liste des modèles.
 *
 * Sorties de la page pour la garder lisible : les sept définitions y pesaient
 * plus que l'écran lui-même, et on ne voyait plus ni les filtres, ni les
 * actions de ligne.
 *
 * Les mêmes colonnes servent les trois rayons — messages, factures, BL. Un jeu
 * par rayon aurait fait perdre le repère d'un écran à l'autre pour ne gagner
 * qu'une colonne vide de moins.
 */
export function templateColumns(t: TFunction): Column<Template>[] {
  return [
    {
      key: 'name',
      header: t('templates.fields.name'),
      cell: (row) => <span className="font-medium">{row.name}</span>,
    },
    {
      key: 'code',
      header: t('templates.fields.code'),
      cell: (row) => <span className="font-mono text-sm">{row.code}</span>,
    },
    {
      key: 'templateType',
      header: t('templates.fields.templateType'),
      cell: (row) => t(`templateTypes.${row.templateType}`),
    },
    {
      key: 'scope',
      header: t('templates.fields.customer'),
      cell: (row) =>
        row.customerId === null ? (
          <Badge variant="outline">{t('templates.globalScope')}</Badge>
        ) : (
          (row.customerName ?? row.customerId)
        ),
    },
    {
      key: 'channel',
      header: t('templates.fields.channel'),
      hideOnMobile: true,
      // Un document n'a pas de canal : afficher un tiret plutot qu'un vide dit
      // que c'est voulu, pas que la donnee manque.
      cell: (row) =>
        row.channel === null ? (
          <span className="text-muted-foreground">{t('templates.noChannel')}</span>
        ) : (
          t(`communicationChannels.${row.channel}`)
        ),
    },
    {
      key: 'language',
      header: t('templates.fields.language'),
      hideOnMobile: true,
      cell: (row) => row.language.toUpperCase(),
    },
    {
      key: 'isDefault',
      header: t('templates.fields.isDefault'),
      hideOnMobile: true,
      // Le modele par defaut est celui que la resolution retiendra : le taire
      // obligeait a ouvrir chaque ligne pour savoir lequel sert.
      cell: (row) =>
        row.isDefault ? <Badge variant="secondary">{t('templates.isDefault')}</Badge> : null,
    },
    {
      key: 'isActive',
      header: t('templates.fields.isActive'),
      cell: (row) => <StatusBadge status={row.isActive ? 'active' : 'inactive'} />,
    },
  ]
}

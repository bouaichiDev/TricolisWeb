# Les tables de liste

Un seul composant, un seul rendu : `shared/components/data/DataTable`. Ce
document dit ce qu'un écran de liste déclare, et ce qu'il ne déclare **pas** —
la seconde liste est la plus importante, puisque c'est elle qui garde les
trente-huit tables du projet identiques.

---

## 1. Ce que l'écran déclare

```tsx
<DataTable
  columns={columns}          // les données de la ligne
  rows={data?.data ?? []}
  rowKey={(row) => row.id}
  meta={data?.meta}          // pagination serveur
  isLoading={isPending}
  error={error}
  sort={filters.sort}
  direction={filters.direction}
  onSortChange={...}
  onPageChange={(page) => setFilters((c) => ({ ...c, page }))}
  onPerPageChange={(perPage) => setFilters((c) => ({ ...c, perPage, page: 1 }))}
  onRetry={() => void refetch()}
  actions={(row) => <RowActions actions={[...]} />}
  emptyMessage={t('orders.empty')}
/>
```

Tri, pagination et taille de page sont **délégués au serveur**. Une liste
paginée ne contient qu'une page : trier ou couper ces vingt-cinq lignes dans le
navigateur donnerait un résultat faux dès la deuxième page.

Changer la taille de page **ramène en page 1** — c'est l'écran qui s'en charge,
`page: 1` dans le même geste. Passer de vingt-cinq à cent lignes en restant page
4 demanderait une page qui n'existe plus, donc une table vide.

---

## 2. Ce que l'écran ne déclare pas

**La colonne d'actions.** Elle est construite par `DataTable` à partir de
`actions` : toujours la dernière, alignée à droite, sous l'en-tête « Actions »,
large de ses boutons et pas d'un pixel de plus. Déclarée à la main dans chaque
écran — ce qu'elle était — elle donnait vingt-quatre colonnes légèrement
différentes : `w-24` ici, `w-32` là, `w-40` ailleurs, un en-tête vide dans la
plupart, « Actions » dans deux.

Pour une table dont les colonnes sont fabriquées ailleurs — un
`xxxColumns.tsx` partagé entre une liste et un onglet — `actionsColumn(header,
cell)` construit exactement la même colonne.

---

## 3. La ligne n'est pas cliquable

Elle l'a été, et elle avait un défaut qu'on ne voit qu'à l'usage : **le texte
devenait insélectionnable**. Tenter de copier un numéro de commande ouvrait la
fiche. Une ligne cliquable n'annonce rien non plus à un lecteur d'écran, qui n'y
voit qu'un tableau.

Ce que la ligne faisait, la colonne d'actions le dit :

```tsx
actions={(row) => (
  <RowActions
    actions={[
      { key: 'view', icon: Eye, label: t('common.view'), to: `/orders/${row.id}` },
      {
        key: 'edit',
        icon: Pencil,
        label: t('common.edit'),
        to: `/orders/${row.id}/edit`,
        permission: 'orders.update',
      },
    ]}
  />
)}
```

`onRowClick` existe encore, et sert aux tables qui ouvrent un **panneau** — une
sélection, un tiroir de détail. Jamais pour naviguer vers un écran.

---

## 4. `RowActions`

Une action qui ouvre un écran porte `to` et devient un **lien** : ouvrable dans
un onglet, copiable par le menu contextuel, annoncée comme un lien. Une action
qui déclenche autre chose porte `onClick` et reste un bouton. `navigate()`
n'aurait offert que le clic gauche.

| Champ | Effet |
| --- | --- |
| `permission` | l'action n'est pas rendue sans le droit — masquée, pas grisée |
| `tone: 'danger'` | réservé à l'action destructrice, et à elle seule |
| `disabled` | l'action existe mais ne s'applique pas à cette ligne |

Le clic ne remonte jamais à la ligne : là où une ligne reste cliquable,
« Supprimer » ne doit pas déclencher les deux.

Une action que le serveur refusera n'est pas proposée. Une facture close est
figée : le bouton disparaît plutôt que de mener à un refus, ce qui userait la
confiance qu'on met dans l'écran.

---

## 5. Taille de page

Dix, vingt-cinq, cinquante, cent. Cent est la borne de `ListRequest` côté
serveur, et ce n'est pas arbitraire : au-delà, la page transporte plus de lignes
que personne n'en lit.

Le sélecteur n'apparaît **que** si l'écran passe `onPerPageChange` : la taille
voyage jusqu'au serveur, et un sélecteur qui ne changerait rien vaudrait moins
que pas de sélecteur du tout.

Une table posée avec une taille hors liste — cinq lignes dans un onglet — la
voit ajoutée aux options plutôt que de rendre un sélecteur vide.

---

## 6. Tests

- `shared/components/data/DataTable.test.tsx` — colonne d'actions, `colSpan` de
  l'état vide, sélecteur de taille ;
- `shared/components/data/RowActions.test.tsx` — lien contre bouton, droit
  manquant, propagation du clic ;
- `modules/orders/pages/OrderListPage.test.tsx` — le cas complet sur un écran
  réel.

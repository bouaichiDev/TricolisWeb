# Audit initial — Frontend Phase 10

Relevé du 2 septembre 2026, avant toute correction. Ce document dit **ce qui
est**, pas ce qui devrait être : les corrections viennent après, et chacune
renvoie ici.

## 1. Point de départ

| | |
|---|---|
| Branche de base | `feature/frontend-phase-9-communication-rules`, commit `96452ad` |
| Branche Phase 10 | `feature/frontend-phase-10-final-consolidation` |
| Arbre de travail | propre (seul le prompt Phase 10 était non suivi) |
| Git author / committer | `Badr <bouaichibadr@gmail.com>` |

Les Phases 1 à 9 ne sont **pas fusionnées dans `main`** : la Phase 10 part donc
de la branche Phase 9, comme le §3 l'exige.

## 2. Matrice des phases

Sources : `php artisan route:list`, `PermissionSeeder`, `tests/`,
`frontend/src/modules/`. Les colonnes disent ce qui existe, pas ce qui est bon —
les défauts sont en §4.

| Phase | Domaine | Backend | Frontend | Permissions | Tests back | Tests front | E2E | Statut |
|---|---|---|---|---|---|---|---|---|
| 1 | Administration / Customers | ✅ | ✅ | ✅ | 15 fichiers | 8 | ❌ | à auditer |
| 2 | Orders / Catalogs / Packages / Services | ✅ | ✅ | ✅ | 8 | 24 | ❌ | à auditer |
| 3 | Tracking / POD / Claims | ✅ | ✅ | ✅ | 4 | 3 | ❌ | à auditer |
| 4 | Providers / Drivers / Vehicles | ✅ | ✅ | ✅ | 4 | 5 | ❌ | à auditer |
| 5 | Planning / Tours / Maps | ✅ | ✅ | ✅ | 6 | 6 | ❌ | à auditer |
| 6 | Pricing / Billing / Settlements | ✅ | ✅ | ✅ | 11 | 4 | ❌ | à auditer |
| 7 | Stock | ✅ | ✅ | ✅ | 5 | 8 | ❌ | à auditer |
| 8 | Integrations | ✅ | ✅ | ✅ | 7 | 10 | ❌ | à auditer |
| 9 | Templates / Communications | ✅ | ✅ | ✅ | 6 | 4 | ❌ | à auditer |

Volumétrie globale :

```text
374 routes api/v1        210 permissions semées
108 routes React          43 entrées de menu
1354 tests backend        616 tests frontend
```

## 3. Interdits du §51 — vérification par recherche

Chaque ligne est une recherche réellement exécutée, pas une déclaration.

| Interdit | Résultat |
|---|---|
| `CommunicationTemplate` en runtime | **aucun** — 2 occurrences, toutes deux dans des commentaires historiques |
| `communication_templates` en runtime | **aucun** — hors migrations de création et de renommage |
| Table `invoice_templates` | **aucune** |
| Entité `InvoiceTemplate` | **aucune** — voir la réserve en §4.1 |
| `OrderStop` | **aucun** — 5 occurrences, toutes des commentaires disant qu'il n'existe pas |
| `status_id` sur une table métier | **aucun** |
| `statusId` | 7 occurrences, toutes légitimes — voir §4.2 |
| Autorisation par nom de rôle | **aucune** — voir §4.3 |
| `eval` dans le rendu ou le tarif | **aucun** |
| `dangerouslySetInnerHTML` | **aucun** — 2 occurrences, commentaires expliquant pourquoi il n'est pas employé |
| Secret en `localStorage` | **aucun** |
| `console.log` de débogage | **aucun** |
| `TODO` / `FIXME` / `HACK` | **aucun** |
| `fetch()` direct hors du client HTTP | **aucun** |
| Deuxième client HTTP (axios…) | **aucun** — un seul `shared/api/client.ts` |

## 4. Analyses des occurrences non triviales

Le §16 l'exige : *« ne pas supprimer aveuglément les occurrences legacy —
analyser »*.

### 4.1 `CustomerInvoiceTemplate`

Une occurrence, dans `frontend/src/modules/customers/components/`. C'est un
**composant React** — la carte de la fiche client qui dit quel modèle de facture
s'appliquera — et non une entité ni une table.

Le §11 interdit cependant `CustomerInvoiceTemplate` dans une liste de noms de
modèles, et le §15 demande qu'une recherche globale ne laisse que des migrations
et de la documentation historiques. Un relecteur exécutant cette recherche
tomberait dessus et devrait raisonner. **À renommer** — voir le rapport final.

### 4.2 `statusId`

Sept occurrences, toutes dans `modules/statuses/hooks/useStatuses.ts`. Elles
désignent l'**identifiant d'une ligne du référentiel** `statuses`, employé par
`useStatusTransitions(statusId)` pour lire et écrire les transitions autorisées.

Ce n'est pas la clé étrangère que le §16 interdit : aucune table métier ne porte
`status_id`, et toutes les colonnes `status` restent textuelles. **Conforme.**

### 4.3 `isOwner`

Vingt-et-une occurrences. C'est un **booléen porté par le rattachement**
(`organization_users.is_owner`), pas un nom de rôle. `usePermissions` l'emploie
ainsi :

```ts
const has = (permission: string) => isOwner || granted.has(permission)
```

Le §4 autorise explicitement `permissions`, `scope`, `isSystem` et le contexte
d'organisation. Aucun `role.name === 'Admin'` n'existe. **Conforme.**

### 4.4 `priority`

Cinq occurrences, toutes dans Pricing : `price_rules.priority` et
`price_matrix_rows.priority`, deux colonnes réellement créées par
`2026_08_29_100000_create_pricing_tables`.

Le `priority` interdit est celui du §113 de la Phase 9, sur `CommunicationRule`
et `OrderCommunication`. Vérifié : **aucune de ces deux tables ne le porte.**
**Conforme.**

## 5. Écarts structurels connus, avant audit détaillé

### 5.1 Aucune infrastructure E2E

`frontend/package.json` déclare huit scripts : `dev`, `build`, `lint`,
`preview`, `test`, `test:watch`, `test:coverage`, `typecheck`. **Ni Playwright,
ni Cypress**, aucun répertoire `e2e/`.

Les §32 à §41 décrivent onze scénarios E2E, et le §48 en fait une condition de
READY. C'est l'écart le plus lourd de cette phase : il ne se comble pas par une
correction, mais par l'ajout d'un outil et d'une infrastructure d'exécution.
Traité comme une décision, pas comme un oubli — voir le rapport final.

Le §42 dit par ailleurs *« n'appeler que les scripts réellement présents »*, et
le §44 conditionne l'E2E à ce que *« l'infrastructure le permette »*.

### 5.2 Modules frontend sans test

Neuf modules n'ont aucun test :

```text
audit  auth  contacts  dashboard  organizations  packages  pricing  services  system
```

`pricing` est le plus sérieux : le §30 nomme explicitement l'éditeur de formule
parmi les composants critiques à couvrir, et le §9 exige de tester division par
zéro, variable inconnue, dépassement, syntaxe invalide et injection.

### 5.3 Trois entrées du menu cible sans entrée réelle

Le §17 donne un menu indicatif et demande de *« l'adapter uniquement aux routes
réelles »*. Trois écarts, tous à analyser plutôt qu'à corriger d'office :

| Menu cible | Réalité |
|---|---|
| Clients › Catalogues | Aucune entrée. Les catalogues existent, sous `/customers/:id/catalogs/…` — ils appartiennent à un client, et n'ont pas de liste globale. |
| Exploitation › Types de colis / de regroupement | Une seule entrée `types`, qui couvre les deux référentiels. |
| Administration › Permissions | Aucune route `/permissions` ; les permissions s'attribuent depuis l'écran des rôles. |

### 5.4 Contrôles déjà automatisés

Trois exigences de la Phase 10 sont **déjà tenues par des tests**, ce qui évite
de les auditer à la main :

| Exigence | Test |
|---|---|
| §17 — chaque entrée de menu a une permission et une route réelles | `MenuPermissionConsistencyTest` |
| §22 — IDOR sur les ressources de premier niveau | `OrganizationIsolationTest` |
| §16 — `order_communications.status` textuel, aucun `status_id` | `TemplateTest`, `statuses-global-audit.md` |

## 6. Plan de travail

Dans cet ordre, chacun produisant son document :

1. audit des statuses (§16) ;
2. audit des menus et de la navigation (§17) ;
3. audit des permissions (§21) ;
4. audit des clés de requête et de la couche API (§19) ;
5. audit de types et de contrat d'API (§27) ;
6. audit de la migration Template (§28) ;
7. audit de sécurité (§23) ;
8. audit de performance (§25) ;
9. responsive et accessibilité (§24) ;
10. couverture de routes (§41) ;
11. tests manquants (§30) ;
12. rapport final et checklist de release (§46, §47).

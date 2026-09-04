# Tableau de bord dynamique par rôle — rapport final

`ROLE_BASED_DASHBOARD_READY`

---

## 1. Ce qui a changé

Le tableau de bord affichait quatre cartes écrites en dur — clients, agences,
utilisateurs, rôles — et les comptait en demandant une page d'un seul élément à
quatre listes paginées, pour n'en lire que `meta.total`. Quatre requêtes HTTP,
quatre autorisations, quatre paginations, pour quatre entiers ; et le même écran
pour tout le monde, alors qu'un planificateur n'a que faire du nombre de rôles.

Il se compose désormais par rôle :

```
Organisation active
→ OrganizationUser → UserRoles
→ RoleDashboardConfigurations
→ UNION des widgets
→ INTERSECTION avec les permissions effectives
→ DashboardWidgetRegistry
→ un seul GET /dashboard
```

Un seul appel, soixante widgets disponibles, neuf catégories.

---

## 2. Les trois documents

| Document | Ce qu'il porte |
| --- | --- |
| `docs/backend/dashboard-security.md` | la règle, les trois filtres, les permissions, ce qu'on refuse d'écrire |
| `docs/backend/dashboard-widget-registry.md` | le catalogue widget par widget, comment en ajouter un |
| `docs/frontend/dashboard-role-configuration.md` | l'écran de réglage, le rendu, les états |

---

## 3. Les décisions qui méritent d'être discutées

### `dashboard.configure`, et non `roles.update`

Le réglage jumeau — le menu par rôle — passe par `roles.update`. Une permission
dédiée est donc un **écart** avec le précédent du projet, et il est assumé :
composer un tableau de bord n'accorde aucun droit, chaque widget restant soumis
à sa propre permission. Exiger `roles.update` aurait obligé à confier le pouvoir
de modifier les permissions d'un rôle pour ranger des cartes.

Elle n'est attribuée automatiquement à aucun rôle sinon `admin`, qui reçoit
l'ensemble des permissions d'organisation par `RoleSeeder`.

### Pas de champ `scope` sur les widgets

Le besoin le conditionne à l'existence d'un contexte Agency actif. Il n'y en a
pas : le client n'envoie que `X-Organization-Id`, et aucune route n'accepte
d'agence courante. Déclarer un champ valant `ORGANIZATION` sur les soixante
widgets aurait ajouté une colonne morte et laissé croire qu'un arbitrage existe.
Il s'ajoutera avec le contexte, le jour où il arrivera.

### Des graphes sans bibliothèque

Le besoin demande de réutiliser la bibliothèque de graphes déjà installée. **Il
n'y en a aucune** — seul Leaflet est présent, pour les cartes. `ChartWidget`
dessine une barre de composition en CSS plutôt que d'ajouter une dépendance qui
n'a pas été demandée. Un histogramme temporel ou un nuage de points reposerait
la question, et ce serait alors une décision de dépendance, prise comme telle.

### La palette des graphes est mesurée, pas choisie

Les huit teintes de `--chart-1` … `--chart-8` passent six contrôles sur la
surface réelle des cartes, en clair comme en sombre : bande de clarté, plancher
de saturation, séparation sous daltonisme (ΔE 9,1 au pire couple voisin),
plancher en vision normale (ΔE 19,6) et contraste. Le mode sombre a ses propres
pas — une inversion automatique les ferait sortir de la bande.

Trois conséquences, toutes contre-intuitives et toutes délibérées :

- **la couleur suit la série, jamais son rang** — l'ordre vient du cycle de vie
  du statut, pas de la valeur, sinon un enregistrement de plus repeindrait la
  moitié du graphe ;
- **il n'y a pas de neuvième teinte** — au-delà de huit séries la queue fusionne
  en « Autres », en gris ; une couleur générée serait indistinguable d'une autre
  sous vision altérée ;
- **la légende est aussi le tableau** — chaque valeur est lisible sans survol, et
  la pastille de couleur est à côté du texte, jamais dedans : trois des huit
  teintes n'ont pas le contraste d'un texte.

### `closed_invoices_period_total` ne dessine aucune barre

C'est le seul graphe dans ce cas, et c'est le propos. Une longueur
proportionnelle affirme une comparaison — deux fois plus long, deux fois plus —
et 5 000 CHF ne se rangent pas sur la même règle que 5 000 MAD. On refuse déjà
de sommer les devises dans une valeur unique ; les mettre sur une échelle
commune reviendrait à le faire du regard. Le serveur le déclare par
`mode: 'amounts'` — c'est lui qui sait que `currency_code` sépare des monnaies
incomparables.

### `labelKey` plutôt qu'un libellé

L'API rend des **clés i18n**, pas des libellés français. Le frontend est
entièrement traduit par `fr.json` ; calculer un titre côté serveur aurait figé
le tableau de bord dans une seule langue et créé une seconde source de
vocabulaire à tenir. Un test refuse un widget dont les deux clés ne sont pas
dans `fr.json`.

### Chaque action rapide est un widget

Plutôt qu'une carte unique « Actions rapides » contenant six liens. Les deux se
valaient à l'écran ; celle-ci se règle — un rôle qui ne facture jamais décoche
`new_invoice` sans toucher au reste, là où une carte unique aurait demandé un
second niveau de configuration à l'intérieur d'un widget.

---

## 4. Ce qui n'a pas été construit, et pourquoi

| Écarté | Raison |
| --- | --- |
| `low_stock` | aucune colonne ne porte de seuil ; en inventer un aurait produit une alerte qui parle de la valeur choisie, pas du stock |
| disponibilité chauffeur / véhicule | aucune table ne la porte ; la déduire des tournées du jour donnerait un chiffre faux dès qu'un congé n'y figure pas |
| un total de facturation unique | les factures portent `currency_code` ; la somme de trois monnaies ressemble assez à un chiffre d'affaires pour qu'on la cite sans la vérifier. Le total du mois est un graphe, une barre par devise |
| `open_claims` lu dans `claims.status` | chaîne libre que chaque organisme remplit à sa façon ; `closed_at IS NULL` ne demande l'avis de personne |
| une table `dashboard_widgets` | clé, type, permission et route sont couplés au code ; une table aurait permis de déclarer un widget que rien ne sait calculer |
| une table `alerts` | les alertes sont des projections comptées au moment où on regarde |
| une table `DashboardHistory` | le journal d'audit existe et fait ce travail |
| une configuration par utilisateur | personne ne l'administrerait ; un métier de douze personnes en aurait douze versions divergentes |
| `status` / `status_id` sur la configuration | elle n'a pas de cycle de vie : elle existe ou non, et la réinitialiser est une suppression |
| `organization_id` sur la configuration | le rôle en porte déjà un ; le dupliquer créerait deux vérités sur la même appartenance |

---

## 5. Le point qui fait tout tenir

```
Rôle Bureau : draft_invoices activé
Permission invoices.view retirée
→ la carte disparaît au prochain chargement
→ la configuration, elle, n'a pas bougé
```

Personne n'a eu à toucher au tableau de bord. Si la permission revient, la carte
revient avec elle. L'intersection a lieu **à chaque requête**, jamais une fois à
l'enregistrement — et le widget refusé n'est pas calculé : sa valeur ne figure
pas dans la réponse, pas même masquée.

`tests/Feature/Api/V1/Dashboard/DashboardTest.php` le vérifie sur le **corps
brut** de la réponse, et non sur la liste décodée.

---

## 6. Vérifications

Toutes exécutées sur cette copie de travail, branche
`feature/role-based-dashboard`.

| Commande | Résultat |
| --- | --- |
| `./vendor/bin/pest` | **1508 réussis**, 4672 assertions |
| `./vendor/bin/pint --test` | conforme |
| `php artisan migrate:status` | `2026_09_05_100000_create_role_dashboard_configurations_table` appliquée |
| `php artisan route:list --path=api/v1/dashboard` | 2 routes |
| `npm run typecheck` | conforme |
| `npm run lint` | conforme — aucun avertissement dans `modules/dashboard` |
| `npm run test` | **706 réussis**, 101 fichiers |
| `npm run build` | conforme |

Répartition des tests ajoutés :

| Fichier | Tests |
| --- | --- |
| `Api/V1/Dashboard/DashboardTest.php` | 15 |
| `Api/V1/Identity/RoleDashboardTest.php` | 18 |
| `Hardening/DashboardCatalogueConsistencyTest.php` | 6 |
| `dashboard/pages/DashboardPage.test.tsx` | 7 |
| `dashboard/components/RoleDashboardPanel.test.tsx` | 7 |
| `dashboard/components/widgets/ChartWidget.test.tsx` | 7 |

Le test de cohérence joue **les soixante widgets** sur une base vide et refuse
une donnée `null` : une clé déclarée sans calcul rendrait une carte vide qu'on
prendrait pour une carte à zéro.

### Une correction hors sujet, assumée

`npm run test` échouait avant ce travail : le motif par défaut de Vitest ramasse
`e2e/*.spec.ts`, et `test.describe()` de Playwright y lève. `vite.config.ts`
exclut désormais `e2e/**`. C'est une panne préexistante, sans rapport avec le
tableau de bord, mais elle rendait impossible la vérification que le besoin
demande.

---

## 7. Ce que les parcours de bout en bout ne couvrent pas

`frontend/e2e/role-dashboard.spec.ts` vérifie que le réglage enregistre
réellement — rechargement compris — et que le tableau de bord se rend.

Il **ne** rejoue **pas** le scénario complet « régler un rôle, puis se connecter
avec un compte qui le porte ». Le seul compte qu'un poste de développement sème
est le **propriétaire** de l'organisation : il détient tout sans passer par un
rôle, et n'en porte aucun. Le fabriquer par l'interface — créer un rôle, créer
un membre, lui poser un mot de passe, se reconnecter — aurait fait de ce fichier
un test de la gestion des membres.

Ces combinaisons sont couvertes là où elles se construisent en une ligne, dans
les tests d'API : rôle unique, cumul de rôles, déduplication, intersection des
permissions, retrait d'une permission, isolation entre organisations, et
absence du chiffre dans le corps de la réponse — que ce dernier point, aucun
test de navigateur ne saurait constater.

---

## 8. Reste ouvert

- **La période de `closed_invoices_period_total` est le mois en cours**, non
  réglable. La rendre configurable ajouterait un paramètre à porter dans la
  configuration d'un rôle, pour une question — « combien exactement entre telle
  et telle date » — à laquelle la liste des factures répond mieux.
- **Aucune tendance comparative.** Le serveur ne compare pas deux périodes ; le
  jour où il le fera, `KpiWidget` a la place pour l'afficher.
- **Le contexte Agency**, s'il arrive, demandera un `scope` par widget et un
  quatrième filtre dans `DashboardComposer`.

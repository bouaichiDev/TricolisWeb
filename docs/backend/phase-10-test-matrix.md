# Phase 10 — Matrice de tests

Document exigé par le §30.

---

## 1. Total

```text
737 tests · 2 475 assertions · 100 % au vert
64 fichiers de test
```

Répartition : 717 tests des Phases 1 à 9, **20 ajoutés par la Phase 10**.

## 2. Matrice par module

Légende : ✓ couvert · — sans objet · ○ couvert indirectement

| Module | Tests | CRUD | Validation | Policy | IDOR | Audit | Concurrence | Performance |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Authentification | 12 | ✓ | ✓ | — | ✓ | ✓ | — | — |
| Organisations | 18 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Identité (users, rôles) | 18 | ✓ | ✓ | ✓ | ✓ | ✓ | — | ✓ |
| Agences et dépôts | 12 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Adresses | 16 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Contacts | 13 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Clients | 12 | ✓ | ✓ | ✓ | ✓ | ✓ | — | ✓ |
| Catalogues | 11 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Documents | 12 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Commandes | 34 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Colis | 15 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Fournisseurs | 23 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Chauffeurs | 15 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Flotte | 15 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Planification (tournées) | 93 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Suivi | 27 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Preuves de livraison | 16 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Litiges | 22 | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Facturation | 39 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Décomptes fournisseurs | 19 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Stock | 73 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Intégrations | 32 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Exports | 30 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Communication | 99 + 25 | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Audit | 4 | ○ | ✓ | ✓ | ✓ | — | — | — |
| **Durcissement** | **20** | ○ | ✓ | ✓ | ✓ | ○ | — | ✓ |

## 3. Ce que couvre chaque colonne

**CRUD** — création, lecture, liste, modification partielle, suppression, avec
les refus métier attachés (409 sur l'historique).

**Validation** — champs obligatoires, enums, formats, règles conditionnelles par
canal ou par transport, structures JSON bornées.

**Policy** — un membre **sans aucune permission** se voit refuser chaque action,
puis les permissions sont attachées une à une. Onze fichiers dédiés.

**IDOR** — accès direct à une ressource d'une autre organisation (404), FK
étrangère dans un payload (422), écriture sur une ressource hors périmètre.

**Audit** — présence de l'entrée, alias morphique et non FQCN, absence des
valeurs sensibles.

**Concurrence** — verrous et unicité :

| Zone | Vérification |
|---|---|
| Numérotation des commandes | Séquence verrouillée, pas de doublon |
| Allocation de ligne de colis | `lockForUpdate` sur `OrderLine` |
| Solde de stock | `StockBalanceLocker`, refus si hors transaction |
| Libération de réservation | `lockForUpdate` |
| Unicité de ligne de facture | Double `UNIQUE` sur `order_service_id` |
| Unicité de ligne de décompte | Idem, indépendant |
| Réordonnancement de séquence | Deux passes avec décalage, en transaction |
| Envoi de communication | Idempotence : deux dispatchs, un seul envoi |
| Relance d'export | `lockForUpdate` **ajouté en Phase 10** |

**Performance** — budget de requêtes constant entre 3 et 20 lignes.

## 4. Les vingt tests ajoutés par cette phase

### `OrganizationIsolationTest` — 9 tests

| Test | Vérifie |
|---|---|
| Lecture croisée | **17 ressources** de premier niveau renvoient 404 sur un identifiant d'une autre organisation |
| Liste croisée | Les 17 listes ne contiennent jamais la ressource étrangère |
| Client étranger dans un payload | 422 |
| Commande étrangère dans un payload | 422 |
| Fournisseur étranger dans un payload | 422 |
| Écriture croisée | `PATCH` et `DELETE` renvoient 404, la donnée reste intacte |
| Organisation non membre | 403 |
| En-tête malformé | 422 |
| En-tête absent | 403 sur six routes métier |

### `EndToEndScenarioTest` — 6 tests

Les cinq scénarios du §31, traversés **par l'API seule**, plus un test
d'isolation transversal.

| Scénario | Chaîne | Refus vérifiés en bout de chaîne |
|---|---|---|
| 1 | Commande → service → tournée → arrêt → suivi → POD → facture | Un service ne peut pas être facturé deux fois |
| 2 | Fournisseur → chauffeur → type → véhicule → décompte | — |
| 3 | Article → emplacement → mouvement → solde | Sortie supérieure au disponible : 409, solde inchangé |
| 4 | Modèle → règle → communication → pièce jointe → mise en file | Après mise en file : ni pièce jointe, ni modification |
| 5 | Configuration → export | Configuration ayant produit un export : suppression refusée |
| Isolation | Cinq payloads croisés | 422 sur chacun |

### `QueryBudgetTest` — 5 tests

Cinq listes mesurées à 3 puis 20 lignes : le nombre de requêtes doit être
identique.

## 5. Ce que les tests ne couvrent pas

| Non couvert | Raison |
|---|---|
| Charge et temps de réponse | Relève d'une préproduction alimentée, hors périmètre. |
| Concurrence réelle multi-processus | Les verrous sont vérifiés par leur présence et par l'idempotence observable ; PHPUnit n'exécute pas deux transactions simultanées. |
| Transporteurs SMS, WhatsApp, push | Aucun fournisseur n'est raccordé — ils sont testés dans leur seul comportement réel : l'échec explicite. |
| Déclenchement automatique des règles | Aucun événement n'est émis par les phases antérieures. |
| Callbacks fournisseur | Aucun endpoint n'existe. |
| `CustomerUser` | Classe du diagramme non implémentée. |

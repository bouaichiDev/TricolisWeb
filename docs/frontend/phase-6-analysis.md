# Phase 6 — analyse préalable

Facturation client, décomptes fournisseur, export des factures clôturées.

Ce document dit **ce que le backend contient déjà**, ce qui manque, et les
décisions prises là où la spécification laisse le choix. Il précède le code : la
Phase 6 demande de partir du schéma réel, pas du document.

Relevé le 28 août 2026, sur `feature/frontend-phase-6-billing-exports`, créée
depuis `feature/frontend-phase-5-planning-tours`.

---

## 1. Les sept tables existent, et correspondent au modèle

Aucune migration à écrire pour le cœur de la phase. Les colonnes relevées
correspondent aux §7, §9, §12, §14, §15, §31 et §54 — **sans écart**.

| Table | Migration | Remarque |
|---|---|---|
| `invoices` | `2026_08_04_100001` | `created_at` seul, pas d'`updated_at` — conforme au §7 |
| `invoice_lines` | `2026_08_04_100002` | |
| `invoice_line_address_snapshots` | `2026_08_04_100003` | aucune clé étrangère vers `addresses` — conforme au §12 |
| `provider_settlements` | `2026_08_04_100004` | |
| `provider_settlement_lines` | `2026_08_04_100005` | pas de `tax_rate` — conforme au §15 |
| `customer_export_configurations` | `2026_08_06_100003` | |
| `export_jobs` | `2026_08_06_100004` | pas de `response_body` ni `http_status` — conforme au §54 |

Aucune des colonnes interdites par les §7, §9, §15 et §54 n'est présente. Le
schéma n'a donc rien à corriger, et le §170 n'a rien à empêcher de ce côté.

### Les deux règles de cardinalité sont déjà tenues en base

```
invoice_lines            → unique('order_service_id')
provider_settlement_lines → unique('order_service_id')
```

Les §10 et §16 sont donc protégés par la base elle-même, et pas seulement par du
code applicatif. C'est le niveau de garantie le plus fort : une écriture
concurrente ne peut pas facturer deux fois le même service. Reste à traduire la
violation en réponse métier lisible plutôt qu'en erreur 500.

---

## 2. Ce qui est déjà implémenté

**Modules** `Billing`, `ProviderSettlements`, `Exports`, `Integrations` — avec
Models, DTOs, Actions, Queries et gardes de périmètre.

**Actions existantes**

```
CreateInvoiceAction          AddInvoiceLineAction
UpdateInvoiceAction          UpdateInvoiceLineAction
DeleteInvoiceAction          RemoveInvoiceLineAction
CalculateInvoiceLineTotals   RecalculateInvoiceTotals

CreateProviderSettlementAction     AddProviderSettlementLineAction
UpdateProviderSettlementAction     UpdateProviderSettlementLineAction
DeleteProviderSettlementAction     RemoveProviderSettlementLineAction
CalculateProviderSettlementLineTotal
RecalculateProviderSettlementTotals

CreateExportJobAction   RetryExportJobAction
ManageExportConfigurationAction
```

**Routes** — CRUD complet pour `invoices`, `invoices/{invoice}/lines`,
`provider-settlements` et leurs lignes, `customer-export-configurations`,
`customers/{customer}/export-configurations`, `export-jobs` et
`export-jobs/{exportJob}/retry`.

**Permissions** — déjà semées : `invoices.*`, `invoice_lines.*`,
`provider_settlements.*`, `customer_export_configurations.*`, `export_jobs.view`,
`export_jobs.create`, `export_jobs.retry`.

**Énumérations** — `ExportFormat` (`xml`, `csv`, `json`, `pdf`) et
`ExportTransport` (`ftp`, `sftp`, `rest_api`, `email`, `manual`) : exactement les
listes des §32 et §33, en minuscules.

---

## 3. Ce qui manque

Par ordre de dépendance.

| # | Manque | Exigé par |
|---|---|---|
| 1 | Codes de statut au référentiel — **aucun** pour `invoice`, `invoice_line`, `provider_settlement`, `export_job` | §18, §57, §107 |
| 2 | `CloseInvoiceAction` + `POST /invoices/{invoice}/close` | §24, §25 |
| 3 | Immutabilité d'une facture clôturée, côté serveur | §22 |
| 4 | Requête des services **facturables** | §42, §43 |
| 5 | Requête des services **réglables** au fournisseur | §102, §17 |
| 6 | `ProcessExportJob` : génération, stockage, transmission | §60, §87 |
| 7 | Générateurs `InvoiceJsonExporter`, `InvoiceXmlExporter` | §83, §84 |
| 8 | Transporteurs REST / FTP / SFTP | §86 |
| 9 | Permission `invoices.close` | §110, §111 |
| 10 | Tout le module frontend `billing` | §38 à §53, §99 à §101 |

---

## 4. Les statuts : rien au référentiel, tout dans les fabriques

C'est l'écart le plus important, et il faut le regarder en face.

**Le référentiel `statuses` ne contient aucun code** pour les quatre sources de
cette phase. `StatusSeeder` sème les statuts des commandes, tournées,
fournisseurs, chauffeurs, véhicules et types — pas ceux de la facturation.

En revanche, les **fabriques de test** emploient déjà des codes :

```
InvoiceFactory            status = draft
InvoiceLineFactory        status = billable
ExportJobFactory          status = pending | sent | failed
ProviderSettlementFactory status = draft
```

Ces codes sont donc la convention réelle du projet, même si personne ne les a
déclarés. Le §19 demande de réutiliser un code existant pour « clôturée » s'il en
existe un : **il n'y en a pas**. Il faut donc le créer.

### Décision — codes retenus

Suivant la casse employée partout ailleurs dans le projet (`draft`, `planned`,
`ready_to_plan`), les codes sont en **minuscules**, contrairement aux exemples du
document qui les écrit en majuscules. La base ne contient encore aucune facture :
le choix est libre, et la cohérence avec le reste prime.

| Source | Codes |
|---|---|
| `invoice` | `draft` → Brouillon, `closed` → Clôturée |
| `invoice_line` | `billable` → Facturable |
| `provider_settlement` | `draft` → Brouillon, `closed` → Clôturé |
| `export_job` | `pending`, `processing`, `sent`, `failed` |

Le code de la clôture est donc **`closed`**, source `invoice`.

`processing` est ajouté au §57 : le moteur en a besoin pour distinguer un envoi
en cours d'un envoi jamais tenté, sans quoi une reprise ne saurait pas si un
transfert est en vol.

Une transition `draft → closed` est semée dans `status_transitions`, comme pour
les commandes et les tournées.

---

## 5. Prix client et coût fournisseur ne se rencontrent pas

`order_services` porte `customer_unit_price` et `customer_total_price`. Ce sont
les prix **client** : ils alimentent `invoice_lines.unit_price`.

`provider_settlement_lines.unit_cost` est un champ distinct, sans source
automatique dans le schéma. Le §103 interdit de le déduire du prix client.

**Décision** : le coût unitaire est **saisi** à la création de la ligne de
décompte, sans valeur par défaut tirée du prix client. Le §169 interdit un moteur
tarifaire, et deviner un coût à partir d'un prix de vente produirait une marge
inventée.

---

## 6. Le fournisseur à payer, face à la replanification

Le §17 pose le vrai problème : un `OrderService` peut avoir plusieurs
`TourStopService` historiques, chez des fournisseurs différents. Payer « le
dernier » paierait une tentative échouée.

Le modèle donne la réponse sans qu'on ait à l'inventer : `TourStopService` porte
`is_active_assignment`. Une seule affectation est active par service — c'est la
règle du §32 de la Phase 5, protégée transactionnellement.

**Règle retenue** : le fournisseur éligible est celui de la tournée qui porte
**l'affectation active** du service. Les affectations désactivées racontent où le
service est passé, pas qui l'a exécuté.

Une tournée sans fournisseur — le transporteur roule lui-même — ne rend donc
aucun service réglable : il n'y a personne à payer.

---

## 7. Services facturables : la règle vient du statut du service

Le §43 interdit de coder en dur `status == COMPLETED` sans vérifier. Le relevé
donne `OrderServiceStatus` : `draft`, `pending`, `ready_to_plan`, `planned`,
`in_progress`, `completed`, `failed`, `cancelled`, `invoiced`.

**`invoiced` existe déjà.** C'est la marque que le projet a prévue pour un
service facturé.

**Règle retenue** — un service est facturable quand :

1. son statut est `completed` — la prestation est faite ;
2. il n'a pas déjà de `invoice_line` — garanti par l'unicité, vérifié en amont
   pour ne pas proposer ce qui sera refusé ;
3. sa commande appartient au client de la facture ;
4. sa commande appartient à l'organisation active.

Un service `failed` ou `cancelled` n'est pas facturable : on ne facture pas ce
qu'on n'a pas livré. Un service `invoiced` non plus, par construction.

---

## 8. Immutabilité d'une facture clôturée

Le §22 énumère ce qu'il faut interdire, et le §683 précise : *« ne pas se
contenter de désactiver les boutons React »*.

**Décision** : un garde `guardOpenInvoice` sur les écritures — mise à jour,
suppression, ajout, modification et retrait de ligne — refusant **422** quand la
facture est `closed`. Le même modèle que `guardDraftOwner` de la Phase 5.

Pas de réouverture (§23), pas d'avoir (§168).

---

## 9. Export : un moteur, deux formats, trois transports

L'architecture du §85, sans duplication :

```
Invoice → InvoiceExportData → exporteur (JSON | XML) → fichier
                                                          ↓
                                       transporteur (REST_API | FTP | SFTP)
```

`InvoiceExportData` est un DTO, pas une table (§63). Le mapping vient de
`customer_export_configurations.settings`, déclaratif, sur une liste blanche de
variables (§66, §67) — aucune expression évaluée.

### Sécurité

- `encrypted_password` : chiffré, jamais rendu, jamais journalisé. La ressource
  expose `hasPassword` (§78).
- `storage_path` reste interne ; le téléchargement passe par un endpoint contrôlé
  (§61).
- REST : timeout, pas de redirection suivie, contrôle du schéma et de l'hôte
  contre le SSRF (§74, §125).
- `remote_directory` et `file_name_pattern` validés contre la traversée de chemin
  (§80, §81).

### Le secret REST — écart à consigner

Le §72 demande de vérifier s'il existe un emplacement de secret pour REST. Le
modèle n'a que `encrypted_password`, prévu pour FTP/SFTP. Le §73 interdit de
détourner `CustomerApiConfiguration.apiKeyHash`, qui est un **hachage**, donc non
réversible.

**Décision** : `encrypted_password` sert d'emplacement au secret REST, puisqu'il
est déjà chiffré et jamais exposé. C'est la convention la moins mauvaise ; créer
une colonne demanderait une validation de conception que le §72 refuse de
présumer. Les options non secrètes vivent dans `settings`.

### Codes de déclenchement

Aucun code n'existe pour l'export de facture. Retenus, en minuscules par
cohérence :

```
export_type = invoice
frequency   = on_invoice_closed
```

`entity_type` d'un `ExportJob` de facture vaut `invoice`, l'alias déjà présent
dans `MorphMap` (§55) — jamais un nom de classe PHP.

---

## 10. Clôture : ce qui se passe, et dans quel ordre

Le §25 impose que le réseau reste hors de la transaction.

```
BEGIN
  verrouiller la facture
  vérifier organisation, permission, statut, au moins une ligne
  passer le statut à « closed »
  chercher les configurations actives du client, type invoice, déclenchement
    on_invoice_closed
  créer un ExportJob par configuration, sans doublon
  journaliser
COMMIT
  puis mettre les jobs en file
```

**Idempotence** (§30) : une facture déjà `closed` rend son état sans recréer de
job. Un job existant pour `(configuration, entity_type, entity_id)` est réutilisé
plutôt que dupliqué.

**Échec d'envoi** (§27) : la facture reste `closed`. L'échec appartient à
`ExportJob.status`, `error_message` et `attempt_count`.

**Aucune configuration** (§28) : la clôture réussit, zéro job, et l'écran le dit.

---

## 11. Ce que je ne ferai pas

Conformément aux §166 à §170 : pas de portail client, pas de paiement, pas
d'avoir, pas de moteur tarifaire, aucune des tables interdites, aucun `status_id`.

L'export des décomptes fournisseur n'est pas branché sur le moteur client
(§108) : le besoin porte sur les factures.

---

## 12. Ordre de livraison prévu

Chaque tranche est vérifiable seule.

1. Statuts au référentiel, transition `draft → closed`, permission `invoices.close`.
2. Clôture : action, route, immutabilité, tests.
3. Services facturables : requête, endpoint, tests.
4. Frontend facturation : liste, fiche, création avec sélecteur de services, clôture.
5. Moteur d'export : DTO, exporteurs JSON/XML, `ProcessExportJob`, tests.
6. Transporteurs REST, FTP, SFTP, avec tests sur doublure.
7. Frontend exports : configurations par client, historique des envois, reprise.
8. Décomptes fournisseur : services réglables, écrans, tests.
9. Menu, rapport final.

---

# 13. Tarification — analyse (§169CA)

La V2 de la phase lève l'exclusion du moteur tarifaire. Cette section relève ce
qui existait, ce qui a été conçu, et pourquoi.

## 13.1 Tarifs existants

**Aucun.** Ni table, ni code : `SHOW TABLES` ne rend rien sur `pric|tarif|rate|
matrix`, et aucune classe du projet ne mentionne un barème. Les prix vivaient
sur `order_services.customer_unit_price`, saisis à la commande.

Il n'y a donc pas de reprise de données à faire, ni de compatibilité à tenir
avec un ancien mécanisme.

## 13.2 Mise à jour de la conception (§169A)

Le diagramme interne — `Conception/diagramme/Tricolis V2 — Diagramme de classes
plateforme interne.txt` — porte désormais un paquet **Tarification client**
avec les sept classes et leurs relations. Il a été écrit **avant** les
migrations, comme le §169A l'impose.

**Un écart de nomenclature :** la spécification cite
`Conception/diagramme/01-diagramme-plateforme-interne.puml`. Ce fichier n'existe
pas ; les diagrammes de classes du projet sont deux `.txt` PlantUML. Le paquet a
été ajouté au fichier réel, sans renommage — renommer des sources de conception
au passage aurait cassé les références des phases précédentes.

## 13.3 → 13.9 Schémas

| Table | Ce qu'elle porte |
|---|---|
| `price_lists` | code, nom, `scope` (`global`/`customer`), validité, `is_active` |
| `customer_price_lists` | le rattachement d'une liste à un client |
| `price_rules` | `service_id` facultatif, **formule obligatoire**, priorité, `is_active` |
| `price_rule_conditions` | variable, opérateur, deux bornes |
| `price_matrices` | dimension (`postal_code`), service facultatif |
| `price_matrix_rows` | zone, `match_mode`, bornes, règle désignée, priorité |
| `pricing_calculations` | l'historique : formule et variables recopiées, résultat |

**`is_active` plutôt qu'un `status`.** Ces tables n'ont pas de cycle de vie
métier — pas de transitions, pas d'états intermédiaires. Le §169BK refuse
justement d'ajouter un `status` artificiel : il aurait fallu le décrire au
référentiel sans qu'aucune machine ne l'anime.

**Une liste peut servir plusieurs clients.** D'où la table de liaison plutôt
qu'un `customer_id` sur la liste : un groupe qui négocie pour ses enseignes ne
duplique pas ses règles.

**Les références tarifaires d'un calcul passent à nul, jamais en cascade.**
Supprimer une règle ne doit pas effacer l'explication d'un prix déjà facturé.

## 13.10 Repli global (§169P)

`PricingResolver` cherche d'abord les listes `customer` rattachées au client,
puis les listes `global`. Dans chaque ensemble : les matrices d'abord, les
règles nues ensuite.

## 13.11 Surcharge client

Une règle client l'emporte sur son équivalent global. **Le repli reste
partiel** : un client qui a négocié la livraison garde le barème général pour le
chargement. Le §169CC l'exige, et un test le verrouille.

## 13.12 Correspondance du service

`service_id` nul vaut « toute prestation que mes conditions acceptent ». À
priorité égale, la règle qui **nomme** le service passe avant la générique, puis
le code départage — aucune « première ligne SQL » (§169AE), et deux calculs
identiques rendent le même prix.

## 13.13 Grammaire des formules

```
formule   := terme (('+' | '-') terme)*
terme     := facteur (('*' | '/') facteur)*
facteur   := nombre | '{P:nom}' | '{V:nombre}' | '(' formule ')' | '-' facteur
```

Un nombre nu (`25`) est accepté en plus de `{V:25}` : refuser la forme que tout
le monde écrit n'aurait servi à rien.

## 13.14 Liste blanche des variables

`poids`, `volume`, `quantite`, `nombre_colis`, `duree`, `distance`.

Les dimensions `code_postal`, `ville`, `pays`, `service` servent aux conditions
et aux matrices — on ne multiplie pas un code postal.

**`distance` n'a aucune source par prestation.** La tournée porte une distance,
mais elle vaut pour le trajet entier, pas pour un arrêt. Une formule qui la
nomme échoue clairement plutôt que de rendre un prix bâti sur la mauvaise
valeur.

## 13.15 Sécurité de la formule (§169G)

Le tokenizer **n'accepte que ce qu'il connaît** : nombres, `{P:}`, `{V:}`,
quatre opérateurs, parenthèses. C'est l'inverse d'une liste noire — une liste de
motifs dangereux s'oublie toujours quelque part.

L'arbre ne porte que trois formes de nœud : nombre, variable, opération binaire.
**Il n'y a pas de nœud « appel de fonction »**, donc rien à appeler. Le §169G
tient par construction, pas par vigilance.

Bornes explicites : 500 caractères, 20 niveaux d'imbrication, division par zéro,
variable absente, montant hors de portée. Chacune rend une erreur métier — le
§169I refuse un tarif approximatif.

## 13.16 Précision monétaire (§169J)

BCMath, déjà présent : aucune dépendance ajoutée.

- **scale de travail** : 6 décimales ;
- **arrondi final** : 2 décimales, au plus proche, la moitié vers le haut ;
- **une seule fois, à la fin** : arrondir entre une division et une
  multiplication fausserait « par tranche de 100 kg ».

`0.1 + 0.2` rend `0.30`, là où un flottant donnerait `0.30000000000000004`.

## 13.17 Matrice facultative (§169Z)

Un tarif au poids n'a besoin d'aucune matrice. Le résolveur consulte les
matrices d'abord, puis les règles nues.

**Une règle citée par une matrice ne s'applique que par elle.** Un test l'a
imposé : sans cette règle, un code postal hors de toute zone retombait sur la
même règle par la porte d'à côté, et les bornes du barème ne voulaient plus rien
dire. Le lien se lit dans les données — aucune colonne à tenir à jour.

## 13.18 Codes postaux (§169AB)

`match_mode` par zone :

- `numeric` — bornes comparées comme des nombres (`1144 → 4000`) ; borne haute
  absente vaut « et au-delà » ;
- `prefix` — commence par, ce qui préserve `01234` ;
- `exact` — valeur unique, lettres comprises.

Les bornes sont stockées en `varchar` : les convertir en entier perdrait les
zéros de tête.

## 13.19 Chevauchements et priorité (§169AE)

Ordre déterministe, jamais « la première ligne » :

1. portée — client avant global ;
2. matrice avant règle nue ;
3. `priority` croissante ;
4. règle nommant le service avant règle générique ;
5. `code`, stable.

Les plages chevauchantes ne sont pas interdites : elles sont **départagées**, ce
qui évite d'imposer une contrainte que les barèmes réels violent souvent.

## 13.20 Instantané du calcul (§169N)

`pricing_calculations` recopie la formule et les variables. Si la formule change
demain, la facture d'hier continue de s'expliquer par celle qui l'a produite.

Un aperçu — préfacturation, testeur — n'écrit rien : le §169AH l'interdit, et
une table d'historique remplie d'essais n'expliquerait plus rien.

## 13.21 Préfacturation

`GET /pricing/prebilling` rend les prestations facturables avec le tarif que le
barème donnerait, sa portée, sa formule et sa zone. C'est la page qui sert à
**trouver les trous** : une prestation sans tarif s'y voit avant la facture,
pas devant le client.

## 13.22 Intégration à la facture

- **Le barème décide** (§169AK) : dès qu'une ligne porte une prestation, le prix
  envoyé par l'écran est ignoré et le calcul est historisé.
- **Sans barème, la ligne est refusée** (§169AJ), en nommant la prestation.
- **`priceOverride`** est la seule sortie : une décision portée par la requête,
  la « règle explicite » du §169BO. Elle ne contourne rien quand un barème
  existe.
- **Recalcul explicite** (§169AM) : `GET repricing` montre l'écart, `POST
  reprice` l'applique. Une facture clôturée est refusée (§169AN).

## 13.23 Tests

| Sujet | Cas |
|---|---|
| Moteur de formule | 19 unitaires — calcul, refus de code, bornes, précision |
| Résolution | 13 — repli, matrices, absence de tarif, historique |
| API barèmes et formules | 16 |
| Règles et matrices | 13 |
| Prix à la facture | 9 |
| Recalcul | 8 |

Le test du §169D — `({P:poids}/{V:100})*{V:25}` avec 350 kg — rend **87.50**,
la valeur de l'énoncé.


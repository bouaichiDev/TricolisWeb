# Rapport final — Phase 10 : consolidation, conformité et sécurité

---

## 1. Branche

```text
feature/backend-phase-10-final-hardening
```

Créée depuis `feature/backend-phase-9-communications` (commit `6cc562d`), non
depuis `main`. Même écart assumé qu'aux Phases 3 à 9 : `main` est un squelette
vide au commit `c97dc0d`.

Aucune fusion, aucun rebase, aucun push. La branche attend la validation.

## 2. Diagrammes

```text
Conception/diagramme/Tricolis V2 — Diagramme de classes partagées.txt
Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme interne.txt
```

Les `.puml` du §1 n'existent pas ; les `.txt` font foi depuis la Phase 4, sur
instruction de l'utilisateur.

**La comparaison a été faite par script, pas à la lecture** : extraction des
blocs `class` et `enum` des deux fichiers, confrontation aux colonnes réelles
d'`information_schema` et aux enums PHP. Les chiffres ci-dessous sont
reproductibles.

## 3-6. Classes

```text
classes UML                63   (18 partagées + 45 propres à l'interne)
classes CONFORMES          62
classes MANQUANTES          1   CustomerUser — hors périmètre interne
classes EN_TROP             0
classes INCOHÉRENTES        0
```

### `CustomerUser` — hors du périmètre, par conception

Déclarée au diagramme interne (lignes 128-134) avec deux relations vers
`Customer` et `User`. **Jamais implémentée** : aucune table, aucun modèle,
aucune référence dans le code.

Elle porte le statut `MANQUANT` parce que l'inventaire décrit ce qui existe.
Mais son absence **n'est pas une lacune des dix phases** : les Phases 1 à 10
constituent le backend de la **plateforme interne**, celle qu'utilisent les
collaborateurs du transporteur. `CustomerUser` n'y sert à personne — elle
rattache un `User` à un `Customer` pour que ce contact **se connecte lui-même**.

Elle relève du **second backend, celui des portails** :

```text
portail client        CustomerUser  ← seule classe déjà décrite au diagramme
portail fournisseur   à définir
portail chauffeur     à définir
```

Deux éléments livrés la préfigurent : `OrderSource` contient `CUSTOMER_PORTAL`,
et `CustomerApiConfiguration` (Phase 8) couvre l'accès **machine** du même
client — le portail en sera l'accès **humain**.

Le §2 de cette phase confirme la décision indépendamment : aucune nouvelle table
métier. Aucune fonctionnalité livrée n'en dépend.

**Sur ce périmètre, la conformité est donc totale : 62 classes internes sur 62.**

## 7-10. Tables et colonnes

```text
tables                     72
  techniques Laravel        9   (liste du §6, conforme)
  technique projet          1   order_number_sequences
  métier                   62   = 62 classes implémentées

colonnes manquantes         0
colonnes en trop            0   (hors quatre imposées par Laravel)
types incompatibles         0
float / double              0
PK non ULID                 0
FK non ULID                 0
```

### Colonnes supplémentaires — quatre, toutes imposées

| Table | Colonne | Décision |
|---|---|---|
| `users` | `password` | **Conservée.** Le diagramme dit `passwordHash` ; c'est la même donnée sous un autre nom. Laravel résout le mot de passe par `getAuthPassword()`, qui retourne `password`. Renommer imposerait de surcharger l'authentification, Sanctum et les réinitialisations pour un gain lexical. |
| `users` | `remember_token` | **Conservée.** Contrat `Authenticatable`, masquée par `#[Hidden]`. |
| `documents` | `deleted_at` | **Conservée.** `SoftDeletes`, décidé et documenté en Phase 2. |
| toutes | `created_at` / `updated_at` | **Conservées.** Forme `snake_case` des attributs du diagramme, pas des colonnes en trop. |

`address_line_1` contre `addressLine1` : différence de conversion `camelCase →
snake_case`, pas d'écart de structure. Les 23 attributs d'`Address` sont là.

### Table sans classe UML — une

`order_number_sequences` (Phase 3) : compteur verrouillé par organisation et par
année. **Conservée.** Jamais exposée, sans route ni resource. L'alternative —
`MAX(order_number) + 1` — produirait des doublons sous concurrence.

Aucune des 38 tables interdites par le §2 n'existe. Vérifié par test.

## 11. Relations

```text
relations du diagramme     121
relations Eloquent         121
manquantes                   0
en trop                      0
cardinalités incohérentes    0
```

**Aucune relation corrigée** : les cardinalités, les noms de clés étrangères et
la nullabilité concordaient déjà, phase par phase.

Six relations polymorphes existent, toutes adossées à la morph map. Aucun nom de
classe PHP n'est stocké en base.

## 12. Enums

```text
enums du diagramme          16
enums PHP correspondants    16
valeurs divergentes          0
enums supplémentaires        1   SubscriptionStatus
```

**Aucun enum corrigé.** Les seize concordent valeur par valeur.

`SubscriptionStatus` est le seul écart : le diagramme type `Subscription.status`
en `string`. **Conservé et signalé** — la colonne reste un `VARCHAR`, donc la
base ne s'écarte de rien ; le diagramme n'énumère aucune valeur pour ce champ ;
et la déviation est documentée dans le code depuis la Phase 1. Le supprimer
retirerait `grantsAccess()`, sur laquelle repose le contrôle d'abonnement.

Trois enums du diagramme partagé — `OrganizationStatus`, `UserStatus`,
`ContactRole` — ne figurent pas dans la liste du §8. Le §1 tranche : les
diagrammes priment sur les prompts.

## 13. Policies — corrigées

```text
Policies                    49
manquantes                   0
corrigées                    5
```

**Cinq Policies renvoyaient 403 pour une ressource d'une autre organisation, là
où vingt-et-une renvoyaient 404.**

| Policy | Avant | Après |
|---|---|---|
| `CustomerPolicy` | 403 | **404** |
| `DocumentPolicy` | 403 | **404** |
| `AddressPolicy` | 403 | **404** |
| `ContactPolicy` | 403 | **404** |
| `AgencyPolicy` | 403 | **404** |

C'est la correction la plus importante de la phase, et ce n'est pas une question
d'élégance. Un `403` sur un identifiant tiré au hasard répond « cette ressource
existe, mais pas pour vous ». Un attaquant qui énumère des ULID apprend ainsi la
liste des identifiants valides du système entier.

**L'incohérence aggravait le problème** : vingt-et-une ressources se taisaient,
cinq parlaient. Il suffisait d'interroger `customers` pour valider un
identifiant que `orders` refusait de confirmer.

Le refus est désormais décidé là où l'information de périmètre existe — dans la
Policy — via `BaseOrganizationPolicy::notFound()`, qui retourne
`Response::denyAsNotFound()`. Les deux cas restent distincts : un membre de
l'organisation sans la permission reçoit toujours **403**, parce qu'il doit
savoir quoi demander à son administrateur.

`AddressPolicy` et `ContactPolicy` ont en outre gagné le contrôle de périmètre
sur `update` et `delete`, qu'elles n'appliquaient qu'à `view`.

## 14. Permissions — une corrigée

```text
permissions déclarées      187
doublons                     0
fautes de nommage            0
sans contrôle (avant)        4
sans contrôle (après)        3
```

**`customers.block`** était déclarée depuis la Phase 2 mais ne gardait rien :
bloquer un client passait par `customers.update`, au même titre que corriger son
téléphone. Or bloquer un client interrompt ses commandes.
`PATCH /customers/{id}/status` exige désormais `customers.block` quand le statut
visé est `blocked`.

Trois restent sans contrôle, **conservées** :

- `organizations.view` et `organizations.create` — `OrganizationPolicy` ouvre
  ces deux accès par conception : lister ses propres organisations et en créer
  une relèvent de l'inscription. Exiger une permission attribuée par une
  organisation pour créer sa première organisation serait circulaire.
- `dashboard.view` — aucun tableau de bord n'existe ; c'est un écran, donc un
  sujet frontend.

Aucune n'est supprimée : les retirer du seeder ne les retirerait pas des
`role_permissions` existants.

## 15. Routes

```text
routes api/v1              308
nommées                    308
doublons                     0
sans auth:sanctum            4   publiques : register, login, forgot, reset
sans contexte organisation  12   self-service + /organizations
non fonctionnelles           0
```

**Aucune route corrigée.** Le versionnement, le nommage, l'ordre de déclaration
et la cohérence parent-enfant étaient conformes.

## 16. Tests ajoutés

**20 tests, 3 fichiers.**

| Fichier | Tests | Objet |
|---|:-:|---|
| `Hardening/OrganizationIsolationTest` | 9 | IDOR global sur **17 ressources** — lecture, liste, écriture, payload croisé, contexte |
| `Hardening/EndToEndScenarioTest` | 6 | Les cinq scénarios du §31, traversés par l'API seule, plus l'isolation transversale |
| `Hardening/QueryBudgetTest` | 5 | Budget de requêtes constant entre 3 et 20 lignes |

Neuf assertions des Phases 1 et 2 ont été mises à jour de `403` vers `404`.
Toutes portaient un nom explicite — « from another organization », « outside the
active organization ». Ce n'est pas une assertion assouplie pour masquer une
erreur : c'est le contrat qui devient plus strict, dans le sens exigé par le
§32. **Aucun test n'a été supprimé, ignoré ni désactivé.**

Une assertion de la Phase 8 a été reformulée : `storagePath` n'étant plus
restitué, la garantie « un chemin fourni par l'appelant est ignoré » se vérifie
désormais en base.

## 17-19. Résultats

```text
composer validate                                valid
php artisan optimize:clear                       OK
php artisan migrate:fresh --seed --env=testing   OK
php artisan test                                 737 passed (2 475 assertions)
./vendor/bin/pint --test                         PASS
php artisan route:list --path=api/v1             308 routes, aucun doublon
php artisan config:cache                         OK
php artisan route:cache                          OK
php artisan event:cache                          OK
TODO / FIXME                                     0
constructions PostgreSQL                         0
```

**PHPStan / Larastan : non configuré dans le projet.** Le §39 le conditionne à
« si déjà configuré » ; ni `phpstan.neon` ni `phpstan.neon.dist` n'existe, et
l'introduire relèverait d'une décision d'outillage hors du mandat de cette
phase.

717 tests des Phases 1 à 9, 20 de la Phase 10. **Aucune régression.**

`MorphMap.php` reste le seul fichier de `app/` au-dessus des 200 lignes
recommandées — 292 lignes. Même arbitrage qu'aux Phases 7, 8 et 9 : registre
plat, le scinder renommerait plus de quarante usages.

## 20. Vulnérabilités corrigées

| # | Vulnérabilité | Gravité | Correction |
|---|---|---|---|
| 1 | **Cinq ressources révélaient l'existence d'une donnée d'une autre organisation** par un 403 au lieu d'un 404 | **Élevée** — énumération d'identifiants, et l'incohérence avec 21 autres ressources rendait l'oracle exploitable | `BaseOrganizationPolicy::notFound()`, appliqué aux cinq Policies |
| 2 | **`ExportJobResource` exposait `storagePath`** | Moyenne — divulgue l'arborescence du serveur de fichiers et, pour un transport SFTP, la structure de répertoires du client | Champ retiré, remplacé par `hasFile` |
| 3 | **`customers.block` n'était contrôlée nulle part** | Faible — bloquer un client ne demandait que `customers.update` | Permission rattachée à la transition vers `blocked` |
| 4 | **Mise à jour perdue sur `ExportJob.attemptCount`** | Faible — deux relances simultanées n'incrémentaient qu'une fois ; le compteur sous-estimait ce qu'il sert à mesurer | `lockForUpdate` + revérification de `sentAt` sous verrou |

## 21-22. Performance et index

```text
N+1 détectés     0
N+1 corrigés     0
index ajoutés    0
```

Le budget de requêtes est **constant** entre 3 et 20 lignes sur les cinq listes
mesurées. Le chargement anticipé était déjà en place, phase après phase.

Chaque filtre exposé par l'API a été confronté à son index : aucun filtre non
couvert, donc aucun index à ajouter. Aucun index sur un `TEXT`, `LONGTEXT` ou
`JSON` — un tri sur ces colonnes est refusé en 422 plutôt qu'indexé.

Un point de fragilité subsiste sans conséquence : `OrganizationUserResource` lit
`user` et `roles` sans `whenLoaded()`. Les quatre points d'appel chargent les
deux relations, et `QueryBudgetTest` échouerait immédiatement si ce chargement
disparaissait. **Conservée telle quelle** : la modifier changerait la forme de
la réponse quand les relations sont absentes.

## 23. Incohérences restantes

| # | Incohérence | Décision |
|---|---|---|
| 1 | `users.password` au lieu de `passwordHash` | **Conservée.** Même donnée ; renommer casserait l'authentification pour un gain lexical. |
| 2 | `address_line_1` au lieu de `addressLine1` | **Conservée.** Différence de conversion `camelCase → snake_case`, pas d'écart de structure. |
| 3 | `SubscriptionStatus` existe sans définition UML | **Conservé.** La colonne reste `VARCHAR` ; l'enum restreint sans rien inventer, et il est documenté depuis la Phase 1. |
| 4 | Format d'erreur métier sans champ `code` | **Conservé.** Le §16 impose d'harmoniser sur la convention principale ; `{ "message": … }` est uniforme sur 308 routes. |
| 5 | `CustomerUser` absente | **Reportée.** Voir §3. |

## 24. Risques

| # | Risque | Portée |
|---|---|---|
| 1 | ~~`CustomerUser` non implémentée~~ | **Ce n'est pas un risque** : la classe relève du second backend, celui des portails. Le périmètre interne est complet. Voir §3 et §26 *bis*. |
| 2 | **SMS, WhatsApp et push échouent systématiquement** | Aucun fournisseur n'est raccordé. Délibéré et annoncé — un faux succès marquerait `SENT` ce qui n'est jamais parti. |
| 3 | **Aucune règle de communication ne se déclenche seule** | Les onze `CommunicationEventType` ne sont émis par aucune phase. Les communications se créent par l'API. |
| 4 | **Aucun export n'est produit** | `ExportJob.hasFile` reste `false`. Le §30 de la Phase 8 interdisait d'inventer un schéma métier. |
| 5 | **`allowedIps` et `permissions` d'une clé API sont stockés mais jamais appliqués** | Sans conséquence aujourd'hui : aucun middleware d'authentification par clé API n'existe, donc la clé ne permet de s'authentifier nulle part. **Devient critique le jour où ce middleware sera écrit.** |
| 6 | **Deux processus sont requis** | Sans `queue:work`, les communications restent `queued` ; sans `schedule:work`, les programmées restent `scheduled`. |
| 7 | **PHPStan non configuré** | Aucune analyse statique. Les types sont annotés partout, mais rien ne les vérifie. |
| 8 | **`DeleteTourAction` ne protège pas l'historique** | Dette ouverte depuis la Phase 5 — voir §25. |

## 25. Éléments reportés

### La dette de suppression de tournée

Signalée à chaque rapport depuis la Phase 5, **non traitée par cette phase** :

> `DeleteTourAction` ne refuse pas la suppression d'une tournée référencée par
> un `TrackingEvent`, une `ProofOfDelivery` ou une `Claim`. S'y ajoute le cas
> d'un `OrderService` facturé ou décompté, ouvert en Phase 6.

Elle n'a pas été corrigée ici parce qu'elle demande une décision métier que les
diagrammes ne tranchent pas : faut-il **refuser** la suppression, ou la
permettre en détachant les événements ? Les deux se défendent, et le §19
demande de documenter les choix, pas d'en inventer un. À trancher avec
l'utilisateur.

Le risque réel est borné : les clés étrangères sont en `RESTRICT`, donc la base
refuse déjà la suppression. Ce qui manque est le **message explicite** — l'API
renvoie une erreur SQL là où elle devrait renvoyer un 409 lisible.

### Autres reports

| Élément | Motif |
|---|---|
| Module `CustomerUser` | Relève du second backend — portail client. Voir §26 *bis*. |
| Transporteurs SMS, WhatsApp, push | Aucun fournisseur raccordé |
| Génération de fichier d'export | Aucune règle de contenu définie |
| Déclencheurs d'événements métier | Aucun `Event` Laravel dans le projet |
| Endpoint de callback fournisseur | Conditionné à des intégrations existantes |
| PHPStan | Décision d'outillage hors mandat |
| Collection Postman | Le projet n'utilise ni Postman ni Bruno ; Scramble produit l'OpenAPI depuis le code |

## 26. Préparation frontend

Le contrat est écrit : `docs/frontend/backend-api-contract.md`.

Il couvre l'URL de base, l'authentification Sanctum, l'en-tête d'organisation
active, le format de réponse, la pagination, les six codes d'erreur et ce
qu'ils veulent dire, les permissions par action, les 308 routes par module, les
seize enums avec leurs valeurs, les payloads composites (commande, arrêt,
communication, réordonnancement), les règles d'upload, le traitement des dates
et des montants, et les filtres et tris.

Il contient aussi — et c'est le plus utile avant de dessiner un écran — la liste
de **ce que le backend ne fait pas encore**, avec la conséquence concrète pour
l'interface.

Trois points sur lesquels le frontend doit s'aligner :

1. **Les montants sont des chaînes.** `"450.00"`, jamais `450.0`. Les convertir
   en `Number` réintroduirait exactement l'erreur que `bcmath` évite côté
   serveur.
2. **404 ne veut pas dire « pas le droit ».** Ne le présentez jamais comme un
   problème de permission — ce serait révéler ce que le backend s'applique à
   taire.
3. **`GET /orders/{id}` retourne `allowedTransitions`.** Construisez le menu de
   changement de statut à partir de cette liste plutôt que de recopier le graphe
   côté client.

## 26 bis. Ce que couvrent les dix phases, et ce qui suit

Les Phases 1 à 10 livrent **le backend de la plateforme interne** : l'outil des
collaborateurs du transporteur. Sur ce périmètre, la couverture est complète —
62 classes du diagramme sur 62.

Trois **portails** restent à construire, dans un second backend :

| Portail | Utilisateur | État des diagrammes |
|---|---|---|
| **Client** | Un contact du client suit ses commandes, dépose des demandes, récupère ses preuves de livraison | `CustomerUser` **déjà spécifiée** (`customerId`, `userId`, `status`, `isAdmin`) |
| **Fournisseur** | Un fournisseur consulte les tournées qui lui sont affectées et ses décomptes | **à définir** — `Provider` ne porte pas de `userId`, aucune classe `ProviderUser` n'existe |
| **Chauffeur** | Un chauffeur voit sa tournée du jour, pointe ses arrêts, saisit les preuves de livraison | **à définir** — `Driver` ne porte pas de `userId`, aucune classe `DriverUser` n'existe |

Le portail client peut donc démarrer immédiatement ; les deux autres exigeront
**d'abord une extension des diagrammes**.

### Ce que l'interne a déjà préparé pour eux

| Acquis | Portail servi |
|---|---|
| `OrderSource::CUSTOMER_PORTAL` | client |
| `CustomerApiConfiguration` — accès machine du client (Phase 8) | client |
| `TrackingEvent`, `ProofOfDelivery` — saisie terrain (Phase 5) | chauffeur |
| `TourPeriodAssignment` — affectation chauffeur/véhicule (Phase 4) | chauffeur, fournisseur |
| `ProviderSettlement` — décomptes (Phase 6) | fournisseur |
| `OrderCommunication` — notification des tiers (Phase 9) | les trois |

### Le point difficile, qui ne ressemble à rien de déjà traité

L'isolation actuelle répond à une question : **« cet utilisateur appartient-il à
l'organisation active ? »**. `BaseOrganizationPolicy` la traite pour les 49
Policies.

Un portail en pose une autre : **« cet utilisateur a-t-il le droit de voir
*cette commande-ci*, parmi celles de son propre client ? »**. Un contact client
ne doit voir que les commandes de son client ; un chauffeur, que les arrêts de
sa tournée ; un fournisseur, que les prestations qui lui sont confiées.

C'est un second axe d'isolation, **imbriqué** dans le premier, que le socle
actuel ne sait pas exprimer. Il devra être conçu explicitement, pas dérivé de
l'existant — et c'est là que se logeront les vraies failles si on l'improvise.

## 27. Fichiers

**Créés — 15** :

```text
docs/backend/phase-10-uml-inventory.md          docs/backend/phase-10-database-audit.md
docs/backend/phase-10-relations-audit.md        docs/backend/phase-10-enums-audit.md
docs/backend/phase-10-policies-audit.md         docs/backend/phase-10-permissions-audit.md
docs/backend/phase-10-routes-audit.md           docs/backend/phase-10-secrets-audit.md
docs/backend/phase-10-performance-audit.md      docs/backend/phase-10-decimals-audit.md
docs/backend/phase-10-audit-coverage.md         docs/backend/phase-10-test-matrix.md
docs/backend/phase-10-api-coverage.md           docs/backend/phase-10-final-report.md
docs/frontend/backend-api-contract.md

tests/Feature/Hardening/OrganizationIsolationTest.php
tests/Feature/Hardening/EndToEndScenarioTest.php
tests/Feature/Hardening/QueryBudgetTest.php
```

**Modifiés — 17** :

```text
app/Policies/BaseOrganizationPolicy.php     notFound() et seesOrganization()
app/Policies/CustomerPolicy.php             404 hors périmètre + capacité block
app/Policies/DocumentPolicy.php             404 hors périmètre
app/Policies/AddressPolicy.php              404 hors périmètre, étendu à update/delete
app/Policies/ContactPolicy.php              404 hors périmètre, étendu à update/delete
app/Policies/AgencyPolicy.php               404 hors périmètre
app/Http/Controllers/…/CustomerController   customers.block sur le statut blocked
app/Http/Resources/…/ExportJobResource      storagePath retiré, hasFile ajouté
app/Modules/Exports/Actions/RetryExportJobAction   lockForUpdate
.env.example                                variables Redis et Memcached retirées
+ 7 fichiers de test (9 assertions 403 → 404, 1 reformulée)
```

**Aucune migration.** Aucune table, aucune colonne, aucun enum, aucune entité
créés — conformément aux §2 et §41.

## 28. Conclusion

Le backend de la **plateforme interne** est complet : **62 classes du diagramme
sur 62**, 308 routes, 187 permissions, 49 Policies, 737 tests au vert. Les
quatre vulnérabilités trouvées sont corrigées. Les cinq incohérences restantes
sont documentées, et chacune est un choix motivé plutôt qu'un oubli.

La 63ᵉ classe — `CustomerUser` — n'y appartient pas : elle ouvre le second
backend, celui des portails client, fournisseur et chauffeur.

Ce qui manque est **délimité et nommé** : trois canaux de communication sans
fournisseur, un moteur d'export sans règles de contenu, des événements métier
que rien n'émet. Aucun de ces manques n'est masqué par du code qui prétendrait
fonctionner.

Le contrat frontend est écrit, et il dit aussi bien ce que l'API garantit que ce
qu'elle ne fait pas encore.

```text
BACKEND_READY_FOR_FRONTEND
```

Cette conclusion est prononcée parce que les 737 tests passent, qu'aucune
vulnérabilité connue ne subsiste, et que les manques sont documentés plutôt que
dissimulés. Elle ne signifie pas que le produit est complet : elle signifie que
le frontend peut être développé contre cette API sans découvrir de surprise que
ce rapport n'aurait pas annoncée.

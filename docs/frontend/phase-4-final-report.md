# Frontend Phase 4 — Rapport final

Fournisseurs, chauffeurs, véhicules, et normalisation des statuts par le
référentiel `statuses`.

## 1. Branches

- **Base** : `feature/frontend-phase-3-order-followup`, dernier commit `86cedc5`.
- **Phase 4** : `feature/frontend-phase-4-providers-resources`.
- Aucun merge, aucun push automatique.

## 2. Identité Git

Auteur et committer : `Badr <bouaichibadr@gmail.com>`, l'identité humaine déjà
présente dans l'historique. Aucun commit n'est attribué à un assistant, aucun
`Co-authored-by` ni `Generated-by` n'est ajouté.

> Note sur le §2 du prompt : le bloc « interdiction absolue » y liste six fois le
> nom du propriétaire du dépôt. Un remplacement automatique a manifestement
> écrasé les mentions d'assistant. La règle appliquée est celle du projet :
> attribution au propriétaire humain, jamais à un assistant.

## 3. Ce qui est livré

### Fournisseurs

Liste (recherche, filtre de statut, tri sur `code`, `name`, `status`), création,
modification, fiche à quatre onglets — Informations, Chauffeurs, Véhicules,
Documents. L'adresse et le contact se lisent dans Informations et se choisissent au
formulaire : le fournisseur en porte **un seul de chaque**, par colonne directe,
et non par liaison polymorphe. « Aucune » détache, en envoyant `null`.

### Chauffeurs

Liste (recherche, filtre par fournisseur, filtre de statut), création avec
fournisseur préremplissable par `?providerId=`, modification, fiche.

### Véhicules

Liste (recherche, filtres fournisseur, type et statut), création, modification,
fiche avec les trois capacités regroupées dans une carte.

### Types de véhicule

**Aucune page dédiée.** Le référentiel a été fusionné dans `types` /
`type_items` le 26 août 2026 (commit `5417ecd`), à la demande explicite du
propriétaire du projet. Un type de véhicule est une valeur de la source
`vehicle`, administrée depuis l'écran `/types`. Recréer `/resources/vehicle-types`
aurait défait ce qui venait d'être fait.

## 4. Routes frontend

```text
/providers   /providers/create   /providers/:id   /providers/:id/edit
/drivers     /drivers/create     /drivers/:id     /drivers/:id/edit
/vehicles    /vehicles/create    /vehicles/:id    /vehicles/:id/edit
/types                                   (référentiels, livré en Phase 3+)
```

Routes à plat, comme le reste de l'application. Le prompt propose `/resources/…`
mais autorise le préfixe existant (§20) ; en introduire un second ferait
cohabiter deux conventions d'URL.

## 5. Endpoints backend consommés

```text
GET|POST /providers   GET|PATCH|DELETE /providers/{id}
GET|POST /drivers     GET|PATCH|DELETE /drivers/{id}
GET|POST /vehicles    GET|PATCH|DELETE /vehicles/{id}
GET      /type-items?type=vehicle
GET      /statuses?source=<entité>&active=1
```

## 6. Permissions

`providers.*`, `drivers.*`, `vehicles.*`, `types.*`, `statuses.view` — toutes
relevées dans `PermissionSeeder`, aucune inventée. Les permissions
`vehicle_types.*` n'existent plus depuis la fusion des référentiels.

## 7. Schéma réellement utilisé et écarts avec l'UML

Les quatre schémas réels et les dix écarts constatés sont détaillés dans
`docs/frontend/phase-4-analysis.md`. Les principaux :

| Attendu au prompt | Réel |
|---|---|
| `Provider.providerType`, `legacyId` | absents |
| `Driver.firstName` / `lastName` / `phone` / `email` / `userId` | un seul `name`, pas de lien `User` |
| Entité `VehicleType` | fusionnée dans `type_items` |
| `statuses.src` | `statuses.source` |
| `statuses.color` / `background_color` | absents ; `icon` seul |
| `statuses` scopé par organisation | portée plateforme |

Aucun écart n'a été corrigé en silence, aucune colonne n'a été ajoutée.

## 8. Référentiel des statuts

- **Audit de schéma** : `docs/backend/statuses-schema-audit.md`.
- **Audit global des 38 colonnes `status`** : `docs/backend/statuses-global-audit.md`.
- **Statuts créés** : `docs/backend/phase-4-statuses-report.md`.

`status` reste **textuel** dans chaque table source — `varchar(32)` en `utf8mb4`.
Aucun `status_id`, aucune clé étrangère, aucune conversion en entier. L'API rend
toujours `"status": "active"`.

Douze entrées ont été créées pour `provider`, `driver`, `vehicle`, `type` et
`type_item`. Les codes viennent du domaine : `active` est la seule valeur en
base, `inactive` son pendant déjà employé par le projet, `blocked` et
`maintenance` sont exercés par la suite de tests elle-même.

### Validation backend

`ExistsInStatusReferential(source)` refuse en 422 tout code absent du
référentiel, emprunté à une autre source, ou désactivé. Appliquée aux cinq
entités ci-dessus. Elle n'est **pas** activée sur les 33 autres : ce serait
refuser du jour au lendemain des écritures sur des domaines que cette phase ne
touche pas et dont les codes n'ont pas été arrêtés. L'audit global le documente
et la commande le mesure.

### Commande de cohérence

`php artisan tricolis:check-statuses [--source=]` balaie les 38 entités et
signale les valeurs orphelines **sans jamais les supprimer**. Elle en relève 15,
toutes hors périmètre.

## 9. Composants de statut

`useStatusOptions(source)`, `useStatusLabel(source, code)`,
`ReferentialStatusSelect`, `StatusFilterSelect`. `StatusBadge` accepte un
`source` optionnel : avec lui, le libellé vient du référentiel ; sans lui, le
comportement d'origine est conservé pour les champs `status` restés libres.

Le sélecteur envoie le **code**, jamais l'identifiant. Un statut désactivé n'est
plus proposé mais reste affiché s'il est déjà porté par la donnée — le retirer
ferait perdre l'information.

## 10. Query keys

`providerKeys`, `driverKeys`, `vehicleKeys`, `typeKeys`, `statusKeys`.

## 11. Zod

`providerSchema`, `driverSchema`, `vehicleSchema`, `typeFormSchema`. Le champ
`status` est une chaîne, **jamais une union figée** : la liste vient du
référentiel, et la coder ici la ferait diverger.

## 12. Tests

| Suite | Résultat |
|---|---|
| Backend (Pest) | **899 tests, 0 échec** |
| Frontend (Vitest) | **376 tests, 0 échec** |
| Pint | passe sur tout le dépôt |
| TypeScript | `tsc --noEmit` propre |
| oxlint | 0 erreur |
| Build Vite | 1 069 kB, sans erreur |

Nouveaux tests : 7 backend sur l'application du référentiel de statuts, 3 sur le
cloisonnement de l'adresse et du contact, 21 frontend répartis sur les trois
modules.

Aucun test E2E : le projet n'embarque ni Playwright ni Cypress, et en installer
un dépasse le périmètre de cette phase (§53 le subordonne à leur présence).

## 13. Régressions rencontrées et corrigées

1. `FleetTest` a refusé `maintenance` sur un véhicule dès l'activation de la
   validation. Le code manquait au référentiel ; il a été ajouté, ainsi que
   `blocked` pour un fournisseur, découvert de la même façon.
2. `MenuTest` vérifiait qu'un groupe sans enfant visible disparaît en cachant
   `agencies` et `depots`. « Ressources » en compte cinq depuis cette phase : le
   test les cache tous.
3. Mon audit global concluait « aucune valeur orpheline » en raisonnant par
   source. La commande en a trouvé 15, dont `services.status = "active"` alors
   que la source `service` a bien six codes — mais pas celui-là. Le document est
   corrigé.
4. Le fournisseur avait été verrouillé en modification sur une lecture erronée
   des `UpdateRequest`, qui l'acceptent. Déverrouillé.
5. La fiche fournisseur portait un onglet « Adresses » avec le panneau de
   liaisons polymorphes, modèle qui n'est pas le sien. Remplacé par la lecture
   de l'adresse et du contact directs.
6. **Faille de cloisonnement.** `addressId` et `contactId` étaient validés par
   un simple `Rule::exists` : n'importe quel identifiant existant passait, y
   compris celui d'une autre organisation, que la fiche rendait ensuite lisible.
   Ces deux tables n'ont pas d'`organization_id` — elles le tiennent de leurs
   liaisons — et la validation traverse désormais `entityAddresses` et
   `entityContacts`. Trois tests le couvrent. Le défaut existait avant cette
   phase, sur fournisseur comme sur chauffeur.

## 14. Fichiers

31 fichiers créés ou modifiés : 3 modules frontend complets, 2 composants de
statut partagés, 1 règle de validation, 1 commande Artisan, 1 fichier de routes,
4 documents.

## 15. Risques connus

- **15 statuts orphelins** sur les domaines hors périmètre. Aucun impact
  aujourd'hui — la validation n'y est pas activée — mais leur reprise est un
  préalable à l'application de la règle §56 à ces domaines.
- **Pas de couleur au référentiel.** Les teintes des badges restent celles du
  système de design, indexées sur le code. Un statut créé par un administrateur
  avec un code inconnu s'affichera en gris. Ajouter `color` à `statuses`
  résoudrait cela, mais c'est une modification de schéma que cette phase n'a pas
  à décider.
Le troisième risque de la première version de ce rapport — adresse et contact en
lecture seule — est levé : le formulaire les choisit et sait les détacher.

## 16. Phase suivante

Planning et tournées. La règle §56 s'y appliquera d'emblée : `tour_period`,
`tour_stop_service` et `tracking_event` figurent parmi les entités sans statuts
au référentiel.

---

FRONTEND_PHASE_4_READY

# Adresses et contacts d'une entité

Branche : `fix/phase-1-organization-roles-permissions`.

---

## 1. Ce qui était mal représenté

La fiche client proposait un onglet « Contacts » qui n'affichait rien, avec ce
message : *« L'API ne permet pas encore de lister les contacts d'un client. »*

Le diagnostic était incomplet. Ce n'était pas seulement une route manquante : la
**forme** de l'écran ne correspondait pas au modèle. Le diagramme de classes ne
prévoit pas de « contacts du client » flottants.

```
Customer ──< EntityAddress >── Address ──< AddressContact >── Contact
                 addressType                    contactRole
                 isDefault                      isPrimary
```

Un client porte **plusieurs adresses** — livraison, facturation — et chaque
adresse porte **ses propres contacts**. Qui prévenir dépend du lieu : le
magasinier d'un entrepôt n'est pas le comptable du siège. Un site client suit
exactement la même structure.

Le type — livraison, facturation — est porté par la **liaison**, pas par
l'adresse. La même adresse peut servir de lieu de livraison à un client et
d'adresse de facturation à un autre.

---

## 2. Le manque réel

`POST /addresses` acceptait déjà `entityType` / `entityId` pour créer la
liaison. `GET /addresses` ne les acceptait pas.

L'écriture savait donc rattacher une adresse à un client ; la lecture ne savait
pas la retrouver. Il aurait fallu lister toutes les adresses de l'organisation,
puis appeler `GET /addresses/{id}/links` pour chacune — autant de requêtes que
d'adresses, pour reconstituer une information que la base détenait déjà.

Même asymétrie sur `GET /contacts`.

---

## 3. Correction backend

| Fichier | Correction |
| --- | --- |
| `app/Http/Requests/Api/V1/Addresses/ListAddressRequest.php` | **créé** — filtres `entityType` / `entityId` |
| `app/Http/Controllers/Api/V1/Addresses/AddressController.php` | Filtre appliqué ; liaisons chargées |
| `app/Http/Resources/Api/V1/Addresses/AddressResource.php` | Champ `links` |
| `app/Http/Requests/Api/V1/Contacts/ListContactRequest.php` | Mêmes filtres |
| `app/Http/Controllers/Api/V1/Contacts/ContactController.php` | Filtre appliqué ; liaisons chargées |
| `app/Http/Resources/Api/V1/Contacts/ContactResource.php` | Champ `links` |

Les alias acceptés sont ceux de `StoreAddressRequest` — `organization`,
`customer`, `customer_site`, `agency`, `depot` : les deux extrémités de la même
relation parlent le même vocabulaire.

`links` n'apparaît que lorsque le filtre est utilisé, par `whenLoaded()`. La
forme de la réponse reste donc la même qu'avant pour les appels existants, et le
champ suit la convention déjà en place sur `RoleResource::permissions`.

**Aucune table, aucune colonne, aucune route nouvelle.** Le filtre est ajouté là
où son symétrique existait déjà en écriture.

### Isolation

La portée organisationnelle est appliquée **avant** le filtre. Un identifiant
appartenant à une autre organisation ne ramène rien, plutôt que de fuir son
contenu — vérifié par test.

---

## 4. Correction frontend

| Fichier | Rôle |
| --- | --- |
| `src/modules/addresses/hooks/useEntityAddresses.ts` | **créé** — adresses d'une entité, contacts d'une adresse |
| `src/modules/addresses/hooks/useEntityAddressMutations.ts` | **créé** — création, modification, suppression, ajout de contact |
| `src/modules/addresses/components/AddressFormDialog.tsx` | **créé** — saisie d'une adresse et de son type |
| `src/modules/addresses/components/AddressContactDialog.tsx` | **créé** — création puis rattachement d'un contact |
| `src/modules/contacts/api/contacts.api.ts` | **créé** — `POST /contacts` |
| `src/modules/addresses/components/EntityAddressesPanel.tsx` | **créé** — liste des adresses, une carte par liaison |
| `src/modules/addresses/components/AddressContactList.tsx` | **créé** — contacts d'une adresse |
| `src/modules/addresses/components/AddressCard.tsx` | Type de liaison, drapeau par défaut, contacts |
| `src/modules/addresses/api/addresses.api.ts` | `listForEntity`, `contacts`, `attachContact`, `detachContact` |
| `src/modules/addresses/types/address.ts` | `links`, `ADDRESS_TYPES`, `CONTACT_ROLES` |
| `src/modules/contacts/components/EntityContactsTab.tsx` | Remplace le message d'indisponibilité |
| `src/modules/customers/pages/CustomerDetailPage.tsx` | L'onglet « Contacts » devient « Adresses » |
| `src/modules/customerSites/pages/CustomerSiteDetailPage.tsx` | L'adresse du site porte ses contacts |

Une adresse peut porter **deux liaisons vers le même client** avec des types
différents — livraison et facturation. Chacune donne sa propre carte : n'en
afficher qu'une ferait disparaître l'un des deux rôles.

### Vocabulaire des types

`EntityAddress.addressType` est une chaîne libre en base, comme le prévoit le
diagramme. Les valeurs **proposées** par l'interface reprennent celles de
l'énumération `ContactRole`, seul vocabulaire existant pour cette distinction
dans le domaine :

```
delivery   Livraison
billing    Facturation
load       Chargement
operations Exploitation
other      Autre
```

En inventer d'autres produirait des données incohérentes d'un écran à l'autre.
Si votre vocabulaire métier diffère, c'est cette liste qu'il faut ajuster — la
base accepte n'importe quelle chaîne de 64 caractères.

---

## 5. Tests

**Backend** — `tests/Feature/Api/V1/Addresses/EntityScopedListingTest.php`,
12 tests : bornage à l'entité demandée, liaison exposée avec son type, absence
de `links` sans filtre, refus d'un alias hors liste, `entityId` obligatoire avec
`entityType`, isolation face à une entité d'une autre organisation. Mêmes
vérifications pour les contacts.

**Frontend** — 18 tests, deux fichiers :

- `EntityAddressesPanel.test.tsx` — paramètres réellement envoyés, type porté
  par la liaison, une carte par liaison, liaison d'une autre entité écartée,
  contacts affichés, adresse sans contact annoncée, détachement masqué sans
  `addresses.update`, états vide et d'erreur ;
- `AddressMutations.test.tsx` — ajout masqué sans `addresses.create`, type et
  entité envoyés à la création, refus d'une adresse sans ligne postale,
  confirmation avant suppression, ordre des deux appels à l'ajout de contact,
  422 du serveur reporté dans le formulaire.

---

## 6. Résultats

```
Backend   767 tests, 2559 assertions   — passent
Frontend  110 tests   — passent
```

`pint`, `typecheck`, `lint`, `build` : conformes. Aucun fichier au-dessus de
200 lignes.

---

## 7. Ce qui reste ouvert

**Documents.** `GET /documents` n'accepte toujours pas `entityType` /
`entityId` : l'onglet « Documents » de la fiche client conserve son message
d'indisponibilité. La correction serait identique à celle-ci — dites-moi si je
la fais.

**Rattacher un contact déjà existant.** Le dialogue crée toujours un nouveau
contact, puis le rattache. Réutiliser un contact déjà présent dans
l'organisation — le même comptable pour deux adresses — demanderait un
sélecteur adossé à `GET /contacts`. La route existe ; l'écran, non.

**Changer le type sans quitter la carte.** La modification d'une adresse permet
de passer de livraison à facturation, au prix de deux appels : la nouvelle
liaison est créée **avant** que l'ancienne soit retirée, l'API refusant de
supprimer la dernière liaison d'une adresse. Un `PATCH` sur la liaison
simplifierait, mais la route n'existe pas.

# Portail fournisseur — référence de l'API (`/api/v1/provider`)

Contrat réel des routes, relevé dans les FormRequest et les Resources. L'inventaire de sécurité complet (permission,
isolation, débit) est **généré** : `security-route-matrix.md`.

## Conventions

- `Authorization: Bearer <jeton>` (obtenu par `auth/login`), `Accept: application/json`.
- Identifiants : ULID. Champs en camelCase. Dates `Y-m-d`, horodatages ISO 8601.
- Succès : `{ "data": …, "meta": {} }` ; listes paginées : `meta.currentPage`, `perPage` (10 à 100), `total`, `lastPage`.
- Erreurs : `401` (jeton absent ou échu), `403` (`code: provider_access_revoked` = session à abandonner ; sinon permission
  manquante), `404` (ressource étrangère **ou** inexistante, indiscernables), `422` (`errors` par champ), `429` (débit).
- Jamais accepté du client : `providerId`, `organizationId`, `status`, `id`, heures d'exécution, `deliveredAt`,
  `orderId`, identifiants de documents → `422`.

## Authentification

| Méthode | Route | Corps |
|---|---|---|
| POST | `auth/login` | `email`, `password`, `providerId?` — compte rattaché à plusieurs fournisseurs : `409` `provider_selection_required` avec `providers`, puis renvoyer avec `providerId` |
| GET | `auth/invitation?token=` | — |
| POST | `auth/activate` | `token`, `firstName`, `lastName`, `password`, `password_confirmation` |
| POST | `auth/forgot-password` | `email` (réponse neutre) |
| POST | `auth/reset-password` | `token`, `email`, `password`, `password_confirmation` |
| GET | `auth/me` | contexte : compte, fournisseur, permissions |
| POST | `auth/logout` | révoque le jeton de la requête |

## Session

| Méthode | Route | Corps / réponse |
|---|---|---|
| GET | `navigation` | entrées de menu ouvertes par les permissions |
| GET | `dashboard` | widgets selon permissions |
| GET / PUT | `profile` | profil fournisseur (lecture) ; PUT : `firstName`, `lastName`, `phone`, `preferredLanguage` du compte |
| PUT | `profile/password` | `currentPassword`, `password`, `password_confirmation` |

## Flotte

| Méthode | Route | Corps |
|---|---|---|
| GET | `drivers`, `vehicles` | `search?`, `status?` (`active`, `inactive`, `maintenance`), `page?`, `perPage?` |
| GET | `drivers/{id}`, `vehicles/{id}`, `vehicle-types` | — |
| POST | `drivers` | `code`, `firstName`, `lastName`, `email`, `phone?` |
| PATCH | `drivers/{id}` | `firstName?`, `lastName?`, `phone?` (seulement si le compte est propre au chauffeur : `actions.canEditAccount`) |
| POST | `vehicles` | `code`, `registrationNumber`, `vehicleTypeId`, `payloadCapacity`, `volumeCapacity`, `palletCapacity` |
| PATCH | `vehicles/{id}` | mêmes champs, facultatifs |
| POST | `drivers/{id}/activate`, `…/deactivate`, `vehicles/{id}/activate`, `…/deactivate` | — (rejeu sans effet ; refus si affecté à une tournée à venir) |

## Tournées

| Méthode | Route | Corps / paramètres |
|---|---|---|
| GET | `tours` | `from?`, `to?`, `status?`, `state?` (`todo`, `running`, `done`), `search?`, `page?`, `perPage?` |
| GET | `tours/calendar` | `from`, `to` (≤ 62 jours) |
| GET | `tours/{id}` | détail, arrêts, services acquis, `actions` calculées par le serveur |
| PATCH | `tours/{id}/resources` | `driverId?`, `vehicleId?` (`null` retire) |
| POST | `tours/{id}/start`, `tours/{id}/complete` | — |
| POST | `tours/{id}/stops/{stop}/arrive`, `…/complete` | — |
| POST | `tours/{id}/stops/{stop}/skip` | `reason` (3 à 500) |
| GET | `tours/{id}/tracking` | chronologie : `type`, `label`, `occurredAt`, `stopSequence`, `serviceNumber`, `latitude`, `longitude` |
| GET | `tours/{id}/documents` | documents d'exécution |
| GET | `documents/{id}` | fichier (flux, `attachment`, `nosniff`) |
| GET | `tours/{id}/stops/{stop}/proofs` | preuves de l'arrêt |
| POST | `tours/{id}/stops/{stop}/proofs` | multipart : `orderServiceId`, `recipientName` (2 à 120), `signature` (PNG/JPEG/WebP ≤ 5 Mo, ≤ 4000 px), `photo?`, `remark?` (≤ 500) |

## Communications et décomptes (lecture seule)

| Méthode | Route | Réponse |
|---|---|---|
| GET | `communications`, `communications/{id}` | canal, objet, statut, dates ; au détail : texte et pièces jointes (noms) |
| GET | `settlements`, `settlements/{id}` | numéro, période, statut, sous-total, taxes, total ; au détail : lignes |
| GET | `settlements/{id}/documents`, `settlements/{id}/documents/{document}` | liste, fichier |

## Exemple

```bash
TOKEN=$(curl -s -X POST "$API/api/v1/provider/auth/login" -H 'Accept: application/json' \
  -d email=admin@transports-alpha.test -d password=… | jq -r .data.token)
curl -s "$API/api/v1/provider/tours?state=todo" -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

Collection Postman / OpenAPI : non générée (aucun outil de génération dans le projet) ; ce document et la matrice
générée en tiennent lieu.

# Portail fournisseur — matrice d'accès (phases 1 à 6)

## Routes du portail — `api/v1/provider`

| Méthode | Route | Chaîne | Permission | Limiteur |
|---|---|---|---|---|
| POST | `auth/login` | publique | — | `provider-portal-login` + 5 essais/min IP+e-mail |
| POST | `auth/forgot-password` | publique | — | 6/min |
| POST | `auth/reset-password` | publique | — | 6/min |
| GET | `auth/invitation` | publique (jeton d'invitation) | — | 20/min |
| POST | `auth/activate` | publique (jeton d'invitation) | — | 10/min |
| POST | `auth/logout` | `auth:sanctum` → `provider.portal` | `provider.portal.access` | `provider-portal` |
| GET | `auth/me` | idem | `provider.portal.access` | `provider-portal` |
| GET | `navigation` | idem | `provider.portal.access` | `provider-portal` |
| GET | `dashboard` | idem | `provider.dashboard.view` | `provider-portal` |
| GET | `profile` | idem | `provider.profile.view` | `provider-portal` |
| PUT | `profile` | idem | `provider.portal.access` (son propre compte) | `provider-portal` |
| PUT | `profile/password` | idem | `provider.portal.access` (son propre compte) | `provider-portal` |
| GET | `tours`, `tours/{id}` | idem | `provider.tours.view` | `provider-portal` |
| GET | `tours/calendar?from&to` (≤ 62 j) | idem | `provider.calendar.view` | `provider-portal` |
| PATCH | `tours/{id}/resources` | idem | `provider.tours.view` + `provider.tours.assign-resources` | `provider-portal-write` |
| POST | `tours/{id}/start`, `tours/{id}/complete` | idem | `provider.tours.view` + `provider.tours.execute` | `provider-portal-execute` (120/min) |
| POST | `tours/{tour}/stops/{stop}/arrive`, `…/complete`, `…/skip` (motif 3..500) | idem | `provider.tours.view` + `provider.tour-stops.update-status` | `provider-portal-execute` |
| GET | `tours/{id}/tracking` | idem | `provider.tours.view` + `provider.tracking.view` | `provider-portal` |
| GET | `tours/{id}/documents` | idem | `provider.tours.view` + `provider.documents.view` | `provider-portal` |
| GET | `documents/{id}` (flux, `nosniff`) | idem | `provider.documents.view` | `provider-portal` |
| GET | `tours/{tour}/stops/{stop}/proofs` | idem | `provider.tours.view` + `provider.pod.view` | `provider-portal` |
| POST | `tours/{tour}/stops/{stop}/proofs` (multipart) | idem | `provider.tours.view` + `provider.pod.create` | `provider-portal-execute` |
| GET | `communications`, `communications/{id}` | idem | `provider.communications.view` | `provider-portal` |
| GET | `settlements`, `settlements/{id}` | idem | `provider.settlements.view` | `provider-portal` |
| GET | `settlements/{id}/documents`, `settlements/{id}/documents/{document}` (flux) | idem | `provider.settlements.view` + `provider.settlements.documents.view` | `provider-portal` |
| GET | `drivers` | idem | `provider.drivers.view` | `provider-portal` |
| GET | `drivers/{id}` | idem | `provider.drivers.view` | `provider-portal` |
| POST | `drivers` | idem | `provider.drivers.create` | `provider-portal-write` (30/min) |
| PATCH | `drivers/{id}` | idem | `provider.drivers.update` | `provider-portal` |
| POST | `drivers/{id}/deactivate`, `drivers/{id}/activate` | idem | `provider.drivers.update` | `provider-portal` |
| GET | `vehicle-types` | idem | `provider.vehicles.view` | `provider-portal` |
| GET | `vehicles`, `vehicles/{id}` | idem | `provider.vehicles.view` | `provider-portal` |
| POST | `vehicles` | idem | `provider.vehicles.create` | `provider-portal-write` (30/min) |
| PATCH | `vehicles/{id}` | idem | `provider.vehicles.update` | `provider-portal` |
| POST | `vehicles/{id}/deactivate`, `vehicles/{id}/activate` | idem | `provider.vehicles.update` | `provider-portal` |

Chauffeurs et véhicules sont toujours lus dans le fournisseur **et** l'organisation
du jeton (`ProviderResourceScope`) : ceux d'un autre fournisseur, du transporteur
ou inexistants répondent le même 404. `providerId`, `organizationId`, `status`,
`userId`, `addressId`, `contactId` sont refusés (422). Un code, un e-mail ou une
immatriculation déjà pris est refusé sans dire par qui. Une ressource affectée à
une tournée planifiée, confirmée ou en cours ne se désactive pas (422).

Tournées : visibles si attribuées au fournisseur dans son organisation, hors brouillon ; sinon 404. Le détail ne
contient aucun prix, coût, facture, numéro de commande, référence externe ni client (liste blanche testée).
Affectation : tournée planifiée ou confirmée, non réservée par un planificateur, ressources du fournisseur et
actives, sans chevauchement de créneau, capacité suffisante ; vérifiée sous verrou puis confiée à `UpdateTourAction`.

Exécution (cycle encadré) : `confirmed → in_progress` (chauffeur et véhicule requis) → `completed` (tous les arrêts
terminés, passés ou annulés) ; arrêts `pending → arrived → completed`, ou `skipped` avec motif audité
(`tour_stop.skipped`). Heure du serveur, verrou tournée puis arrêt, rejeu sans effet ; l'ordre des arrêts n'est pas imposé.
Un arrêt `in_progress` (posé par l'interne) se termine ou se passe. Rien ne démarre ni n'avance tant qu'un planificateur
réserve la tournée (`locked_by`) ; au démarrage, chauffeur et véhicule sont relus (actifs, dans la flotte).

Chronologie : horodatages réels de la tournée et des arrêts, arrêts passés (sans le motif), événements de suivi de
**cette** tournée avec le libellé du référentiel — jamais leur `description`. Documents : seulement par un chemin
jusqu'à une tournée visible — `Tour`, `TourStop`, `OrderService` **acquis** sur un arrêt, `ProofOfDelivery` d'un service
acquis sur cet arrêt (et ses signature et photo) ; jamais un document de la seule commande (facture). Hors chemin : 404.
**Service acquis** (`ProviderAcquiredServices`) : affectation active **et** confirmée. Un service retiré d'une tournée
déjà partie garde sa ligne (inactive) : il sort aussitôt des documents, des preuves et de l'affichage. Un document lié à
un service se partage entre les fournisseurs qui le portent en même temps (plusieurs étapes) : voulu, c'est son bon.
Preuves : tournée en cours ou terminée, non réservée ; arrêt atteint (`arrived`, `in_progress`, `completed`), service
acquis sur cet arrêt, une preuve par service
et par arrêt ; signature obligatoire et photo facultative, images réelles (`image` + `mimes` : ni SVG ni faux PNG,
5 Mo, 4000 px) rattachées à l'arrêt ; heure, commande et documents fixés par le serveur (`deliveredAt` → 422).
Les fichiers déjà écrits sont effacés si l'enregistrement échoue. Liste des preuves : seulement celles d'un service
acquis sur l'arrêt ; la remarque n'est rendue que pour une preuve saisie par un membre de ce fournisseur (celle du
back-office peut être interne).

Communications : seulement celles envoyées à l'adresse du membre connecté (sans casse), dans l'organisation du
fournisseur, canal externe, statut `sent`/`delivered`/`read` ; texte sans balises, pièces jointes par leur nom ;
ni commande, ni destinataire, ni variables, ni réponse du prestataire. Toute autre : 404.
Décomptes : ceux du fournisseur, dans son organisation, **clôturés** (`closed`) ; brouillon, autre fournisseur,
inexistant : 404. Lignes : numéro de service, description, quantité, coût unitaire, total — jamais commande ni
client. Documents : seulement ceux liés au décompte (`DocumentLink → provider_settlement`). Aucune écriture.

Aucune route de gestion d'utilisateurs ni de rôles (vérifié en E2E : 404).

## Routes du back-office — gestion des accès

| Méthode | Route | Policy |
|---|---|---|
| GET | `providers/{provider}/portal-roles` | `providers.view` |
| GET | `providers/{provider}/portal-users` | `providers.view` |
| POST | `providers/{provider}/portal-users/invite` | `providers.update` |
| PATCH | `providers/{provider}/portal-users/{providerUser}` | `providers.update` |
| POST | `providers/{provider}/portal-users/{providerUser}/disable` | `providers.update` |
| POST | `providers/{provider}/portal-users/{providerUser}/enable` | `providers.update` |
| POST | `providers/{provider}/portal-users/{providerUser}/resend-invitation` | `providers.update` |

`{providerUser}` est toujours cherché dans le fournisseur de l'URL : un membre
d'un autre fournisseur répond 404. Ces routes refusent les jetons de portail.

## Rôles par défaut

| Rôle | Base¹ | Flotte : voir | Flotte : écrire | Affecter aux tournées | Exécuter, saisir une preuve | Décomptes |
|---|---|---|---|---|---|---|
| Administrateur fournisseur (`PROVIDER_ADMIN`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Exploitant (`PROVIDER_DISPATCHER`) | ✓ | ✓ | — | ✓ | ✓ | — |
| Gestionnaire flotte (`PROVIDER_FLEET_MANAGER`) | ✓ | ✓ | ✓ | — | — | — |
| Lecture (`PROVIDER_VIEWER`) | ✓ | ✓ | — | — | — | — |

¹ `provider.portal.access`, `provider.dashboard.view`, `provider.profile.view`, `provider.tours.view`, `provider.calendar.view`,
`provider.tracking.view`, `provider.documents.view`, `provider.pod.view`, `provider.communications.view`.

`ProviderPortalSeeder` accorde les permissions nouvelles aux rôles existants au
rejeu : `php artisan db:seed --class=ProviderPortalSeeder --force` après chaque phase.

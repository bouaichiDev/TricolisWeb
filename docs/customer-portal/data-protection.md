# Protection des données — portail client

Ce document décrit **ce que fait l'application** : données traitées, où elles
apparaissent, qui y accède, comment elles se corrigent. Il prépare une exploitation
conforme ; il ne vaut pas avis juridique ni certification. Les points marqués
**À valider** demandent une décision métier ou juridique.

## 1. Catégories de données

| Catégorie | Exemples | Personnes concernées | Source |
|---|---|---|---|
| Comptes du portail | nom, prénom, e-mail, téléphone, empreinte du mot de passe, dernière connexion | employés du client | invitation par le client ou le transporteur |
| Destinataires et contacts | nom, téléphone, e-mail, adresse de livraison, consignes | clients finaux du client | saisie portail, import de fichier, API |
| Commandes | références, articles, quantités, dates, statuts | — (données d'entreprise) | portail, imports, API |
| Preuves de livraison | nom du réceptionnaire, signature, photo, horodatage, remarque | réceptionnaire | application chauffeur |
| Suivi | position du véhicule, ETA, événements | chauffeur (indirectement) | fournisseur de géolocalisation |
| Réclamations et documents | description, pièces jointes | employés du client, tiers cités | portail |
| Traces techniques | adresse IP, compte, action, valeurs avant/après | utilisateurs | `AuditLog`, journaux |
| Intégrations | correspondances, connexions (secrets chiffrés), envois | — | back-office |

Aucune donnée sensible au sens de l'article 9 du RGPD n'est attendue. Une pièce
jointe libre peut en contenir malgré tout : **à encadrer par les conditions
d'utilisation** (À valider).

## 2. Finalités applicatives

Exécuter et suivre les prestations de transport commandées ; prouver la livraison ;
traiter les réclamations ; tenir le stock confié ; échanger les commandes et leurs
statuts avec les systèmes du client ; sécuriser l'accès et tracer les changements
sensibles.

## 3. Où les données personnelles apparaissent

| Zone | Données | Protection |
|---|---|---|
| Écrans du portail | les données du seul client connecté | isolation serveur (voir [security.md](security.md)) |
| Lien public de suivi | référence, statut, chronologie, ETA, nom du transporteur | aucune adresse, aucun nom, aucun identifiant technique |
| `AuditLog` | compte, IP, valeurs modifiées | secrets masqués ; aucune route d'audit dans le portail |
| Journaux applicatifs | identifiants techniques, compteurs, statuts HTTP | secrets masqués ; pas de contenu de fichier ni de payload complet |
| Fichiers d'import déposés | contenu du fichier (destinataires) | disque privé, supprimés après import ou à 30 min |
| Cache | résumé d'import (références, messages d'erreur) | 24 h, lié au client |
| Exports vers le client | statuts de commande, factures | champs listés seulement, aucune donnée de contact |
| Navigateur | jeton d'accès (`localStorage`), cache de requêtes | jeton expirant ; cache vidé à la déconnexion |

## 4. Accès

Le client n'accède qu'à ses données, selon ses permissions ([matrice
d'accès](access-matrix.md)). Le transporteur y accède par le back-office, selon ses
propres rôles. Les systèmes du client accèdent par clé API, bornée par client,
permissions et adresses.

## 5. Correction et droit à l'information

| Besoin | Mécanisme existant |
|---|---|
| Corriger son profil | `PUT /customer/profile` |
| Corriger un membre | administrateur client : `PUT /customer/users/{id}` |
| Désactiver un accès | administrateur client ou transporteur ; jetons révoqués |
| Corriger une commande | tant qu'elle n'est pas engagée (planification, facture) |
| Corriger un destinataire d'une commande engagée | par le transporteur, en interne |
| Exporter les données d'une personne | **aucun mécanisme dédié** — À valider |
| Effacer ou anonymiser une personne | **aucun mécanisme** — voir [retention-decisions.md](retention-decisions.md) |

## 6. Conservation

Aucune durée légale n'est fixée par l'application. L'inventaire de l'existant et
les décisions attendues sont dans [retention-decisions.md](retention-decisions.md).

## 7. Sous-traitance et flux externes

| Flux | Données | Point d'attention |
|---|---|---|
| Fournisseur de géolocalisation | positions des véhicules | contrat de sous-traitance — À valider |
| Géocodage et calcul d'itinéraire | adresses de livraison | idem |
| Tuiles cartographiques (OpenStreetMap par défaut) | zone affichée, IP du navigateur | `referrerPolicy=no-referrer` ; fournisseur à confirmer en production |
| Courriel (relais de l'organisation) | e-mails, noms | configuration par organisation |
| Intégrations clients (FTP/SFTP, REST) | commandes et statuts | destinations choisies par le client, protégées contre le SSRF |

## 8. Points à valider

1. Mention d'information des utilisateurs du portail et des destinataires.
2. Contrats de sous-traitance (géolocalisation, cartographie, hébergement, courriel).
3. Procédure d'export et d'effacement sur demande d'une personne.
4. Encadrement des pièces jointes libres.
5. Durées de conservation (voir document dédié).

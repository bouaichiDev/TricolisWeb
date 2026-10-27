# Décisions de conservation — portail client

**Aucune durée de conservation n'est inventée ici.** Ce document inventorie ce que
l'application fait déjà, ce qui peut techniquement être anonymisé ou désactivé, et
ce qui attend une décision métier ou juridique. La Phase 7 n'a ajouté **aucune**
suppression de données métier.

## 1. Mécanismes existants

| Donnée | Mécanisme | Durée | Nature |
|---|---|---|---|
| Fichiers d'import déposés | suppression après import ; `customer-integrations:prune-uploads` horaire | 30 min | technique |
| Résumé d'un import en file | cache | 24 h | technique |
| Jetons du portail | expiration ; `sanctum:prune-expired --hours=24` quotidien | 12 h + 24 h | sécurité |
| Invitations | expiration du lien | 72 h (`CUSTOMER_PORTAL_INVITATION_HOURS`) | sécurité |
| Liens de réinitialisation | broker Laravel | défaut Laravel | sécurité |
| Liens publics de suivi | révocation ; expiration si une date est choisie | au choix du client | fonctionnel |
| Documents supprimés | suppression logique puis `documents:purge` | 30 j après suppression (`DOCUMENT_RETENTION_DAYS`) ; **commande non planifiée** | à valider |
| Journaux applicatifs | canal `single` (sans rotation) ou `daily` | `daily` : 14 j (`LOG_DAILY_DAYS`) | à valider |

## 2. Données sans mécanisme de suppression

| Donnée | Pourquoi elle est conservée aujourd'hui | Contrainte probable | Décision attendue |
|---|---|---|---|
| Commandes, lignes, colis, prestations | historique d'exploitation, facturation | obligations comptables et contractuelles | durée et sort après échéance |
| Factures et lignes | facturation | conservation comptable légale | durée (juridique) |
| Preuves de livraison (signature, photo, nom) | preuve en cas de litige | délais de réclamation et de prescription | durée, puis suppression ou anonymisation des fichiers |
| Suivi (positions, événements) | suivi et litiges | données de localisation des chauffeurs | durée courte à fixer |
| Mouvements et réservations de stock | traçabilité du stock confié | contrat de logistique | durée |
| Réclamations et pièces jointes | traitement des litiges | prescription | durée |
| `AuditLog` | traçabilité des changements sensibles | sécurité, preuve | durée, puis purge |
| Envois (`ExportJob`) et fichiers générés (`exports/{id}`) | relance, preuve d'envoi | — | durée des fichiers générés |
| Comptes du portail désactivés | historique des auteurs (`created_by`, audit) | intégrité référentielle | anonymisation après délai |

## 3. Ce qui peut être anonymisé ou désactivé sans casser l'historique

- **Compte du portail** : désactivation immédiate (existant). Anonymisation possible
  du nom, de l'e-mail et du téléphone de `users`, le compte restant référencé par
  l'audit et `created_by`. **Non implémentée** : demande une décision et un
  traitement pour le cas d'un compte partagé avec le back-office.
- **Destinataire d'une commande clôturée** : nom, téléphone et e-mail des contacts
  et adresses rattachés aux commandes facturées depuis un délai à fixer.
- **Preuve de livraison** : suppression des fichiers (signature, photo) en gardant
  l'horodatage et le statut.
- **Positions de suivi** : purge au-delà d'un délai court.

Aucune de ces opérations n'est automatisée : chacune attend la décision du §4.

## 4. Décisions nécessaires (métier / juridique)

1. Durée de conservation des commandes, factures et preuves de livraison.
2. Durée de conservation des positions de géolocalisation.
3. Durée de conservation de l'audit et des journaux, et passage au canal `daily`.
4. Planification de `documents:purge`.
5. Durée de conservation des fichiers d'export générés.
6. Procédure d'anonymisation d'un compte ou d'un destinataire sur demande.
7. Fréquence des sauvegardes et durée de conservation des dumps (Phase 8,
   voir `docs/deployment/customer-portal-backup-restore.md`).
8. Durée de conservation de l'historique des imports (`customer_import_runs` :
   références externes et messages des commandes refusées), depuis le 2026-10-10.

Une fois une durée décidée, l'implémentation suivra le patron existant : commande
artisan dédiée, planifiée dans `routes/console.php`, testée, documentée ici —
jamais une suppression silencieuse.

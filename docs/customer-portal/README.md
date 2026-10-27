# Portail client Tricolis — documentation fonctionnelle

Ce document décrit **ce qui existe réellement** dans le portail au 2026-10-10.
Index technique en fin de page.

## 1. Présentation

Le portail client est une application web distincte du back-office du
transporteur. Un **client** du transporteur (IKEA, QOQA…) y suit ses
commandes, son stock et ses flux, sans jamais voir ceux d'un autre client.
Le transporteur décide, client par client, quels modules sont ouverts (stock,
catalogue, suivi) et qui y a accès.

## 2. Authentification

- Connexion par email et mot de passe. Un compte rattaché à plusieurs clients
  choisit le client après connexion.
- Un compte se crée **uniquement par invitation** : l'administrateur du client
  invite, le destinataire reçoit un lien valable 72 h, choisit son mot de passe.
- « Mot de passe oublié » envoie un lien de réinitialisation.
- La session expire après 12 h (`CUSTOMER_PORTAL_TOKEN_TTL_MINUTES`) ; elle
  s'arrête aussi dès que le compte, l'accès, le client ou l'organisation du
  transporteur sont désactivés.
- Connexions répétées freinées par adresse.

## 3. Tableau de bord

Page d'accueil après connexion. Le transporteur compose les widgets par rôle ;
un widget n'apparaît que si l'utilisateur a la permission de son module.

## 4. Utilisateurs

Réservé à l'administrateur du client : liste des membres, invitation, modification,
désactivation, attribution de rôles. Un administrateur n'attribue jamais une
permission qu'il n'a pas lui-même ; le propriétaire du compte ne peut être ni
désactivé ni rétrogradé. Quatre rôles sont fournis : administrateur,
gestionnaire de commandes, gestionnaire de stock, lecteur.

## 5. Commandes

Liste filtrable (recherche, statut, service, dates). Comme toutes les listes
du portail, elle a la structure du back-office : filtres au-dessus, choix du
nombre de lignes (10 à 100), actions de ligne en icônes. Création en étapes :
informations, lignes (articles du catalogue ou lignes libres), colis, services
(adresse, créneau souhaité, contacts), documents joints, récapitulatif.
Détail en onglets (général, services, colis, articles, documents, historique).
Modifier, dupliquer, annuler (avec motif) : proposés seulement quand le statut
le permet et que la commande n'est pas encore planifiée — c'est le serveur qui
décide. Une commande créée est d'abord un **brouillon** côté portail : cela ne
dit rien de sa transmission au transporteur.

## 6. Calendrier

Commandes affichées à leur date (planifiée par le transporteur, sinon
demandée), en vue mois, semaine ou liste, filtrables. Un clic ouvre une vue rapide
avec les liens « Voir la commande », « Modifier », « Suivre ». Les heures
affichées sont celles du créneau sur place, sans conversion de fuseau.

## 7. Suivi

Page de suivi d'une commande : étapes, créneau, position du véhicule quand le
transporteur la partage, et ce qui n'est pas encore connu, dit comme tel.
Exige le module de suivi ouvert au client.

## 8. Lien public

Depuis une commande, l'administrateur crée un lien de suivi à transmettre au
destinataire final : consultable **sans compte**, il montre l'essentiel du
suivi et rien d'interne. Le lien se révoque à tout moment ; un lien inconnu,
expiré ou révoqué répond toujours la même page « introuvable ».

## 9. Réclamations

Depuis un service d'une commande : déclarer un problème (type, description,
pièces jointes), suivre son traitement, compléter tant qu'elle est ouverte.

## 10. Documents, BL, preuves de livraison

Documents joints à la commande, au service ou à la réclamation : consultation
et téléchargement. Preuves de livraison : date, signataire, présence d'une
signature et de photos. Fichiers dangereux refusés au dépôt.

## 11. Stock

Si le transporteur gère le stock du client : articles, soldes par emplacement,
mouvements, réservations par commande. Le client crée un article ou en corrige
les informations, mais **ne modifie jamais une quantité** : il dépose une
demande de correction, que le transporteur valide ou refuse. La demande se
crée depuis la fiche d'un article ou depuis la page « Demandes de correction »
(bouton « Nouvelle demande » : choix de l'article, quantité constatée, motif).

## 12. Catalogue

Si le module est ouvert : catalogues du client, articles (code, code-barres,
désignation, poids, volume, dimensions), fiche avec l'usage de l'article dans
les commandes et le stock. Ajout d'un article par l'administrateur ou le
gestionnaire de stock, qui peuvent aussi **modifier** un article et le
**désactiver** ou le réactiver. Un code ne peut exister deux fois dans un
catalogue. Un article désactivé n'est plus proposé dans les nouvelles
commandes ; les commandes et le stock existants ne changent pas. Pas de
suppression depuis le portail. Un client ne peut jamais lire ni modifier
l'article d'un autre client.

## 13. Intégrations

Les flux du client, configurés par le transporteur : imports automatiques
(fichiers XML par SFTP, API REST relevée toutes les heures), dépôt manuel d'un
fichier Excel/CSV avec prévisualisation, envois de statuts vers le système du
client. Le client voit l'état et l'historique, dépose ses fichiers, relance un
envoi en échec. **Aucun secret n'est jamais affiché** ; la configuration reste
au transporteur.

## 14. Permissions

Chaque action exige une permission `customer.*` ; voir
[`access-matrix.md`](access-matrix.md) (qui fait quoi) et
[`phase-8-permission-matrix.md`](phase-8-permission-matrix.md) (permission par
permission). Ce qui n'est pas permis n'apparaît pas à l'écran, **et** le
serveur le refuse.

## 15. Sécurité

Le client est déterminé par la session, jamais par la requête ; la ressource
d'un autre client répond « introuvable ». Détails : [`security.md`](security.md),
[`data-protection.md`](data-protection.md).

## 16. FAQ / erreurs fréquentes

| Message ou situation | Explication |
|---|---|
| « Accès refusé » | votre rôle n'a pas cette permission : voir votre administrateur |
| Module absent du menu (Stock, Catalogue) | non ouvert à votre entreprise par le transporteur, ou pas dans votre rôle |
| « Commande introuvable » | la commande n'existe pas ou n'appartient pas à votre entreprise |
| Modifier / Annuler absents | la commande est planifiée ou son statut ne le permet plus |
| Session expirée | reconnectez-vous ; la session dure 12 h |
| Lien d'invitation refusé | expiré (72 h) ou déjà utilisé : demander une nouvelle invitation |
| Import : ligne en erreur | le message indique la ligne et le champ ; les commandes valides sont créées, une commande déjà importée est ignorée |
| « Une erreur interne est survenue » | incident côté serveur, sans détail exposé : réessayer, puis contacter le transporteur |

## Index technique

| Sujet | Document |
|---|---|
| Inventaire Phase 8 | [`phase-8-feature-inventory.md`](phase-8-feature-inventory.md) |
| Routes | [`phase-8-route-matrix.md`](phase-8-route-matrix.md), [`security-route-matrix.md`](security-route-matrix.md) (généré) |
| API | [`api.md`](api.md) ; OpenAPI : `/docs/api` (local) ou `php artisan scramble:export` |
| E2E | [`phase-8-e2e-matrix.md`](phase-8-e2e-matrix.md) |
| Non-régression, performance, fuseaux | [`phase-8-regression-report.md`](phase-8-regression-report.md), [`phase-8-performance-report.md`](phase-8-performance-report.md), [`phase-8-timezone-strategy.md`](phase-8-timezone-strategy.md) |
| Conservation | [`retention-decisions.md`](retention-decisions.md) |
| Rapport final | [`phase-8-final-report.md`](phase-8-final-report.md) |
| Exploitation | [`../deployment/customer-portal-runbook.md`](../deployment/customer-portal-runbook.md) |
| Développement | [`../../customer-portal/README.md`](../../customer-portal/README.md) |

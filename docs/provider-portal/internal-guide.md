# Portail fournisseur — guide interne (back-office)

Pour l'équipe du transporteur qui confie des tournées à ses sous-traitants.

## Ouvrir l'accès d'un fournisseur

1. **Fournisseurs → fiche → onglet « Accès portail »** : « Inviter » un membre (e-mail, nom, rôle). Rôles proposés :
   Administrateur fournisseur, Exploitant, Gestionnaire flotte, Lecture (`access-matrix.md`).
2. Le membre reçoit un lien valable `PROVIDER_PORTAL_INVITATION_HOURS` (72 h) ; « Renvoyer » en génère un nouveau.
3. « Désactiver » coupe l'accès **à la requête suivante** ; « Réactiver » le rouvre. Le changement de rôle prend effet
   de même. Toute la gestion des comptes reste ici : le portail n'en propose aucune.
4. Un fournisseur dont le statut n'est plus `active`, ou une organisation suspendue, ferme le portail à tous ses
   membres.

## Confier une tournée

- Dans le formulaire de tournée, champ **« Fournisseur »**. La tournée apparaît au fournisseur dès qu'elle sort du
  brouillon ; la retirer ou la réattribuer la fait disparaître à sa requête suivante.
- Le fournisseur affecte lui-même chauffeur et véhicule tant que la tournée est planifiée ou confirmée et qu'aucun
  planificateur ne la réserve. Il la démarre quand elle est **confirmée** (décision ouverte : voir `README.md`).
- Seuls les services **acquis** (affectés et confirmés) lui sont montrés ; un service retiré d'une tournée déjà partie
  disparaît de son portail, avec ses documents.

## Ce que le fournisseur voit, et ne voit pas

- Voit : arrêts, adresses, consignes, contacts sur place (chargement, livraison, exploitation, urgence), numéros de
  service, colis, poids, volumes ; documents liés à la tournée, à ses arrêts, à ses services acquis, à ses preuves.
- Ne voit jamais : clients, commandes, références, prix, coûts, factures, contacts de facturation.
- **Attention** : tout document **lié** à une tournée, un arrêt, un service acquis ou un décompte clôturé lui devient
  téléchargeable. Les décomptes ne lui sont montrés qu'une fois **clôturés** ; leurs lignes et leurs documents aussi.
- Ses communications : seulement les e-mails envoyés à l'adresse de chaque membre, depuis son rattachement.

## Audit

Connexions, invitations, changements de rôle et de statut sont tracés sous `provider_portal.*` ; les actions
d'exécution sous leurs codes métier (`tour.status_changed`, `tour_stop.skipped`, `proof_of_delivery.created`…).

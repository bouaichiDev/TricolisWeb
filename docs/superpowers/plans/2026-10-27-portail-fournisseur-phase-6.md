# Portail fournisseur — Phase 6 : communications et décomptes (lecture seule)

Spec : `docs/superpowers/specs/2026-10-27-portail-fournisseur-design.md`. Consigne :
`PortailFournisseur/Phase6_06_Communications_Facturation_Suivi.md`. Base : commit de la phase 5.

## Audit de l'existant

- **Communications** : `order_communications` (Phase backend 9). Aucun rôle destinataire « fournisseur »
  (`RecipientRole` : customer, load/delivery/billing contact, internal_user, custom). Le mécanisme qui
  rattache une communication à une personne est son **adresse** (`UserNotifications`, `recipient_email`).
  Aucun accusé de réception pour les canaux externes (`read_at` n'existe que pour l'interne).
- **Décomptes** : `ProviderSettlement` (+ lignes), statuts du référentiel `draft → closed`,
  montants calculés par `RecalculateProviderSettlementTotals` (aucun recalcul côté portail).
  Documents : `DocumentLink → provider_settlement` (alias de la morph map).

## Décisions

- Communications montrées : celles **envoyées à l'adresse du membre connecté** (`recipient_email`, sans casse),
  dans l'organisation du fournisseur, canal externe (jamais `internal_notification`, qui relève du
  back-office), statut `sent`, `delivered` ou `read`. Pièces jointes : nom et type seulement.
  Communications au fournisseur en tant qu'entité, accusés de réception : `BLOCKED_DESIGN_DECISION`.
- Décomptes montrés : ceux du fournisseur courant, dans son organisation, **clôturés** (`closed`).
  Un brouillon est un travail interne dont les montants changent : 404 comme s'il n'existait pas.
- Lignes : service par son **numéro** (jamais commande, client ni prix de vente), description, quantité,
  coût unitaire, total. Statut avec le libellé du référentiel.
- Aucune écriture : ni route POST/PATCH/DELETE, ni action financière.

## Tâches

1. Permissions `provider.communications.view`, `provider.settlements.view`,
   `provider.settlements.documents.view` ; rôles : administrateur tout, exploitant communications,
   gestionnaire flotte et lecture : rien de financier (communications pour tous). Test de rejeu.
2. `ProviderCommunicationsQuery` + `GET communications` (paginé) + `GET communications/{id}` — tests :
   adresse d'un autre, autre organisation, interne, brouillon → absents / 404.
3. `ProviderSettlementScope` + `GET settlements`, `GET settlements/{id}`, `GET settlements/{id}/documents`,
   `GET settlements/{id}/documents/{document}` — tests : autre fournisseur, brouillon, facture client,
   document étranger → 404 ; aucune méthode d'écriture (405/404) ; montants exacts.
4. Navigation (`communications`, `settlements`), pages React liste/détail, Vitest.
5. E2E, documentation, suite complète, commit.

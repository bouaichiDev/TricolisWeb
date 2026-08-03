# Tricolis V2 — Diagrammes de séquence

Cinq séquences tirées du **code réel** : chaque classe nommée ici existe, chaque
refus décrit est vérifié par un test. Elles couvrent les cinq mécaniques qui se
répètent partout ailleurs — sécurité, écriture transactionnelle, concurrence,
asynchrone, et calcul monétaire.

Les diagrammes sont en Mermaid : GitHub les affiche nativement.

---

## 1. Authentification et périmètre organisationnel

**La séquence que traverse chacune des 296 routes métier.** Tout le reste en
dépend : c'est ici que se décide qui voit quoi.

```mermaid
sequenceDiagram
    autonumber
    actor U as Client HTTP
    participant S as Middleware auth Sanctum
    participant M as EnsureOrganizationContext
    participant C as Contrôleur
    participant P as Policy
    participant Q as Query Object
    participant DB as MySQL

    Note over U: POST /auth/login
    U->>C: email + mot de passe
    C->>DB: vérifie l'empreinte bcrypt
    C-->>U: 200 { token, user, organizations[] }

    Note over U,DB: Toute requête métier suivante

    U->>S: Authorization: Bearer …<br/>X-Organization-Id: 01JZ…
    alt jeton absent ou invalide
        S-->>U: 401 Unauthenticated
    end
    S->>M: utilisateur authentifié

    alt en-tête malformé
        M-->>U: 422 identifiant invalide
    else en-tête absent
        M-->>U: 403 en-tête requis
    else non membre de l'organisation
        M-->>U: 403 accès refusé
    end

    M->>C: organization_id injecté dans la requête
    C->>C: requireOrganizationId()
    C->>P: authorize('view', $ressource)

    alt ressource d'une AUTRE organisation
        P-->>C: denyAsNotFound()
        C-->>U: 404 introuvable
    else membre, mais sans la permission
        P-->>C: false
        C-->>U: 403 permission manquante
    end

    P->>C: autorisé
    C->>Q: paginate(profil, filtres, organizationId)
    Q->>DB: SELECT … WHERE organization_id = ? …
    DB-->>Q: lignes
    Q-->>C: LengthAwarePaginator
    C-->>U: 200 { data, meta, links }
```

### Le point à retenir

Les deux refus **ne se ressemblent pas, et c'est délibéré** :

| Situation | Réponse | Ce que l'appelant apprend |
|---|---|---|
| Membre, sans la permission | `403` | « demande `customers.update` à ton administrateur » |
| Ressource d'une autre organisation | `404` | rien — l'existence de l'identifiant n'est pas confirmée |

Cinq Policies renvoyaient `403` dans le second cas jusqu'à la Phase 10. C'était
un oracle d'énumération : un attaquant testant des ULID au hasard apprenait
lesquels étaient valides ailleurs dans le système.

---

## 2. Création d'une commande complète

**Une commande, ses lignes, ses colis et ses services en un seul appel.** C'est
le modèle de toute écriture composite du projet : une Action orchestre, une
transaction englobe, un seul audit conclut.

```mermaid
sequenceDiagram
    autonumber
    actor U as Client HTTP
    participant C as OrderController
    participant R as StoreOrderRequest
    participant A as CreateFullOrder
    participant N as GenerateOrderNumber
    participant L as CreateOrderLines
    participant P as CreateOrderPackages
    participant S as CreateOrderServices
    participant T as RecalculateOrderTotals
    participant AU as WriteAuditLog
    participant DB as MySQL

    U->>C: POST /orders<br/>{ customerId, agencyId, lines[], services[] }
    C->>R: validation
    alt lines[] ou services[] vide
        R-->>U: 422 — une commande exige au moins<br/>une ligne et un service
    else client d'une autre organisation
        R-->>U: 422 customerId
    end
    R->>C: données validées
    C->>C: authorize('create', Order)
    C->>A: execute(CreateOrderData, organizationId, user)

    rect rgba(120, 160, 255, 0.12)
        Note over A,DB: DB::transaction — tout ou rien
        A->>N: execute(organizationId, année)
        N->>DB: SELECT … FOR UPDATE sur la séquence
        Note right of N: le verrou empêche deux commandes<br/>simultanées d'obtenir le même numéro
        N->>DB: increment(last_number)
        N-->>A: « ORD-2026-000001 »
        A->>DB: INSERT orders
        A->>L: execute(order, customer, lines)
        L-->>A: lignes créées
        A->>P: execute(order, packages, lines)
        P-->>A: colis créés
        A->>S: execute(order, services, packages)
        S-->>A: services créés
        A->>T: execute(order)
        T->>DB: UPDATE poids, volume, nombre de colis
        T-->>A: totaux recalculés
        A->>AU: order.created
    end

    A-->>C: Order
    C-->>U: 201 { data avec lines[], services[], packages[] }
```

### Le point à retenir

Le numéro de commande est **attribué par le serveur, sous verrou**. `firstOrCreate`
ne suffisait pas : deux requêtes concurrentes peuvent toutes deux constater
l'absence de la séquence et tenter de la créer. Le code gère explicitement cette
collision.

Si l'une des quatre sous-actions échoue, **rien ne subsiste** — pas de commande
orpheline sans ligne, pas de numéro consommé pour rien.

---

## 3. Mouvement de stock — la concurrence

**Deux sorties simultanées sur le même article ne doivent pas passer sous zéro.**
C'est le cas où l'ordre des opérations décide de la justesse du résultat.

```mermaid
sequenceDiagram
    autonumber
    actor U as Client HTTP
    participant C as StockMovementController
    participant A as CreateStockMovementAction
    participant G as StockScopeGuard
    participant K as StockBalanceLocker
    participant B as RecalculateStockBalance
    participant DB as MySQL

    U->>C: POST /stock-movements<br/>{ stockItemId, sourceLocationId, quantity: 500 }
    C->>A: execute(data, contexte)
    A->>G: article et emplacements dans l'organisation ?
    alt hors périmètre
        G-->>U: 422 stockItemId
    end
    G-->>A: validés
    A->>A: assertStructure()
    alt ni source ni destination
        A-->>U: 422 — un mouvement doit avoir<br/>une origine ou une destination
    end

    rect rgba(255, 170, 120, 0.15)
        Note over A,DB: DB::transaction
        loop emplacements triés par id
            A->>K: lockOrCreate(article, emplacement)
            Note right of K: tri par id = ordre de verrouillage<br/>identique pour tous → pas d'interblocage
            K->>DB: SELECT … FOR UPDATE
            K-->>A: solde verrouillé
        end

        A->>B: assertAvailable(solde, 500)
        alt disponible insuffisant
            B-->>A: StockUnavailable
            A-->>U: 409 — quantité disponible insuffisante
            Note right of B: le solde reste intact :<br/>la transaction est annulée
        end
        B-->>A: suffisant

        A->>DB: INSERT stock_movements
        A->>B: execute(solde source, −500)
        B->>B: quantity ≥ 0, reserved ≥ 0,<br/>reserved ≤ quantity
        B->>DB: UPDATE stock_balances
        B-->>A: solde recalculé
        A->>DB: audit stock_movement.created
    end

    A-->>C: StockMovement
    C-->>U: 201
```

### Le point à retenir

Trois protections se cumulent, et aucune ne remplace les autres :

1. **le verrou** (`SELECT … FOR UPDATE`) sérialise les demandes concurrentes ;
2. **le tri par identifiant** avant verrouillage évite l'interblocage — deux
   transferts croisés A→B et B→A prendraient leurs verrous dans le même ordre ;
3. **`RecalculateStockBalance`** refuse tout solde négatif, même si la
   vérification amont a été franchie.

Le solde n'est jamais écrit à la main : il est **produit par les mouvements**.
C'est pourquoi `stock_balances` n'a ni route de création ni route de
modification.

---

## 4. Communication — du modèle à l'envoi

**La seule séquence asynchrone du projet.** Elle montre comment le contenu est
figé, comment la file est déclenchée, et pourquoi un double envoi est impossible.

```mermaid
sequenceDiagram
    autonumber
    actor U as Client HTTP
    participant C as OrderCommunicationController
    participant A as CreateOrderCommunicationAction
    participant RC as ResolveOrderCommunicationRecipient
    participant RD as CommunicationTemplateRenderer
    participant Q as QueueOrderCommunicationAction
    participant TR as ApplyCommunicationTransition
    participant J as SendOrderCommunicationJob
    participant SD as CommunicationSenderRegistry
    participant DB as MySQL

    U->>C: POST /orders/{order}/communications<br/>{ templateId, recipientRole: "customer" }
    C->>A: execute(data, contexte)

    A->>RC: resolve(rôle, commande, utilisateur)
    Note right of RC: le destinataire vient du RÔLE.<br/>Les coordonnées du payload sont ignorées.
    alt aucun contact ne porte ce rôle
        RC-->>U: 422 recipientRole
    end
    RC-->>A: nom, e-mail, téléphone

    A->>A: le canal exige-t-il ce contact ?
    alt canal email sans adresse
        A-->>U: 422 recipientEmail
    end

    A->>RD: render(modèle, variables)
    alt variable non déclarée, notation à points,<br/>ou expression
        RD-->>U: 422 — rendu refusé
    end
    RD-->>A: { subject, body } — échappés selon le canal
    A->>DB: INSERT — subject/body/variables FIGÉS
    Note right of DB: modifier le modèle plus tard<br/>ne changera jamais ce message
    A-->>C: statut « draft »
    C-->>U: 201

    U->>C: POST /order-communications/{id}/queue
    C->>C: authorize('queue') — distincte d'update
    C->>Q: execute(communication, contexte)
    Q->>TR: transition vers « queued »
    TR->>DB: SELECT … FOR UPDATE puis relit le statut
    alt transition interdite par l'enum
        TR-->>U: 409 — « Envoyée » ne peut pas passer à « En file »
    end
    TR->>DB: UPDATE status, queued_at + audit
    TR-->>Q: verrouillé et écrit
    Q->>J: dispatch(id, organizationId)
    Q-->>C: 200 « queued »

    Note over J,SD: plus tard — php artisan queue:work

    J->>DB: recharge la communication
    alt statut ≠ « queued »
        J-->>J: s'arrête sans rien envoyer
        Note right of J: idempotence : un second dispatch<br/>ne produit pas un second envoi
    end
    J->>TR: transition vers « sending »
    J->>SD: for(canal)
    SD-->>J: transporteur
    alt canal email ou notification interne
        J->>TR: « sent » + providerMessageId + sentAt
    else sms, whatsapp, push
        Note right of SD: aucun fournisseur raccordé —<br/>échec explicite, jamais un faux succès
        J->>TR: « failed » + errorMessage
    end
```

### Le point à retenir

`ApplyCommunicationTransition` **relit le statut en base après avoir pris le
verrou**, jamais depuis l'instance en mémoire. C'est ce qui rend l'envoi
idempotent : un second `dispatch` du même job trouve la communication déjà
partie et s'arrête.

Trois canaux échouent volontairement. Un transporteur qui retournerait « succès »
sans rien envoyer marquerait `SENT` un message qui n'existe pas — pire qu'une
absence annoncée.

---

## 5. Facturation d'un service — l'argent

**Un service exécuté devient une ligne de facture, une seule fois.** La séquence
montre où passent les montants et pourquoi ils ne sont jamais des flottants.

```mermaid
sequenceDiagram
    autonumber
    actor U as Client HTTP
    participant C as InvoiceLineController
    participant A as AddInvoiceLineAction
    participant G as BillingScopeGuard
    participant CA as CalculateInvoiceLineTotals
    participant M as Money bcmath
    participant T as RecalculateInvoiceTotals
    participant DB as MySQL

    U->>C: POST /invoices/{invoice}/lines<br/>{ orderServiceId, quantity: 1, unitPrice: 450 }
    C->>A: execute(facture, data, contexte)

    A->>G: orderService(id, client)
    alt service d'un autre client
        G-->>U: 422 orderServiceId
    end
    G-->>A: OrderService
    A->>G: assertOrderMatchesService()
    Note right of G: la ligne, le service et la commande<br/>doivent désigner le même client

    A->>CA: totals(quantité, prix, remise, taxe)
    CA->>M: bcmul, bcsub, bcadd — échelle 10
    Note right of M: aucun float, aucun round() PHP.<br/>Les montants sont des CHAÎNES.
    M-->>CA: totaux à l'échelle 2
    CA-->>A: { totalExcludingTax, totalIncludingTax }

    rect rgba(140, 200, 150, 0.15)
        Note over A,DB: DB::transaction
        A->>DB: INSERT invoice_lines
        alt ce service est déjà facturé
            DB-->>A: violation de l'UNIQUE sur order_service_id
            A-->>U: 422 — service déjà facturé
            Note right of DB: garantie au niveau base,<br/>pas seulement applicatif
        end
        A->>T: execute(facture)
        T->>DB: SUM des lignes
        T->>M: subtotal, total
        T->>T: taxTotal = total − subtotal
        Note right of T: DÉDUIT, jamais sommé ligne à ligne :<br/>sinon les arrondis s'accumulent et<br/>le total n'égale plus la somme affichée
        T->>DB: UPDATE invoices
        T-->>A: totaux à jour
        A->>DB: audit invoice_line.created
    end

    A-->>C: InvoiceLine
    C-->>U: 201 { data, subtotal: "450.00" }
```

### Le point à retenir

Deux garanties qu'il faut lire ensemble :

- **`order_service_id` est UNIQUE** sur `invoice_lines` **et**, indépendamment,
  sur `provider_settlement_lines`. Un service se facture une fois au client et
  se décompte une fois au fournisseur — les deux contraintes ne sont pas liées,
  puisqu'un même service peut être les deux.
- **`taxTotal = total − subtotal`**, déduit et non additionné. Sommer les taxes
  ligne à ligne accumule un arrondi par ligne, et le total cesse d'égaler la
  somme des deux autres nombres affichés au client.

Les montants restent des **chaînes** de la base jusqu'au JSON : `"450.00"`,
jamais `450.0`. Le frontend ne doit pas les convertir en `Number` — ce serait
réintroduire exactement l'erreur que `bcmath` évite côté serveur.

---

## Ce que ces cinq séquences ne montrent pas

| Absent | Où c'est décrit |
|---|---|
| Génération du fichier d'export | `phase-8-final-report.md` — aucune règle de contenu définie |
| Déclenchement automatique des règles | `phase-9-final-report.md` — aucun événement métier émis |
| Callback fournisseur | `phase-9-final-report.md` — aucune intégration existante |
| Portails client, fournisseur, chauffeur | `phase-10-final-report.md` §26 bis — second backend |

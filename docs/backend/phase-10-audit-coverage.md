# Phase 10 — Couverture de l'audit

Document exigé par le §29.

---

## 1. Structure d'`AuditLog`

Dix colonnes, strictement celles du diagramme partagé. **Aucune modification de
structure** n'a été faite par cette phase, comme le §29 l'impose.

```text
id · organization_id · user_id · action · entity_type · entity_id
old_values · new_values · ip_address · created_at
```

`entity_type` porte un **alias métier** de la morph map, jamais un nom de classe
PHP. Un test le vérifie : `AuditLog::where('entity_type', 'App\Modules\…')`
retourne toujours zéro.

## 2. Couverture par nature d'événement

| Nature | Couverte | Exemples d'actions |
|---|:-:|---|
| Création | ✓ | `customer.created`, `order.created`, `tour.created`, `invoice.created`, `communication_template.created` |
| Modification | ✓ | `*.updated` sur toutes les ressources modifiables |
| Suppression | ✓ | `*.deleted` — écrit **avant** la suppression, pour que les anciennes valeurs existent encore |
| Changement de statut | ✓ | `customer.status_changed`, `order.status_changed`, `order_service.status_changed`, `tour.status_changed` |
| Affectation | ✓ | `tour_period_assignment.created`, `tour_stop_service.deactivated` |
| Stock | ✓ | `stock_movement.created`, `stock_reservation.created`, `stock_reservation.released` |
| Facturation | ✓ | `invoice.*`, `invoice_line.*`, `provider_settlement.*`, `provider_settlement_line.*` |
| Communication | ✓ | `order_communication.queued`, `.sending`, `.sent`, `.failed`, `.cancelled`, `.retried` |
| Export | ✓ | `export_job.created`, `export_job.retried` |
| Sécurité | ✓ | `auth.failed`, `customer_api_configuration.key_rotated` |

**106 fichiers** écrivent dans l'audit, par `WriteAuditLog` directement ou par
`WriteModelAudit`.

## 3. Ce qui n'est jamais audité

Le §29 interdit d'auditer six catégories. Toutes sont expurgées ou absentes :

| Interdit | Traitement |
|---|---|
| Mot de passe | Jamais dans `old_values`/`new_values` — aucune Action ne journalise `password`. |
| Jeton | Idem ; les jetons Sanctum ne passent par aucune Action auditée. |
| Clé API | `api_key_hash` remplacé par `[secret]` (`WriteConfigurationAudit`). |
| Secret | `encrypted_password` remplacé par `[secret]`. |
| Fichier complet | Seul le nom et le type sont journalisés, jamais le contenu. |
| Contenu sensible inutile | `body` et `provider_response` remplacés par `[secret]` (`WriteCommunicationAudit`). |

Le mécanisme est unique depuis cette phase : `App\Shared\Audit\WriteModelAudit`
applique `redact()` sur `old_values` **et** `new_values`, avant écriture. Les
sous-classes ne portent que la liste des colonnes à masquer.

### Pourquoi expurger `body`

Le §39 de la Phase 9 le demandait déjà : « Ne pas dupliquer inutilement body
complet dans tous les audits. » La raison est un droit d'accès, pas une taille :
`audit_logs.view` se consulte plus largement que le module de communication.
Journaliser le corps d'un message y recopierait des données personnelles pour
un lecteur qui n'a pas le droit de lire le message lui-même.

La donnée n'est pas perdue : elle reste sur `order_communications`, lisible avec
`order_communications.view`.

## 4. Les changements réellement journalisés

`WriteModelAudit::update()` compare **avant** et **après** et n'écrit **que si
quelque chose a changé** :

```php
if ($before !== $after) {
    $this->audit->execute(…);
}
```

Un `PATCH` qui renvoie la valeur existante ne produit pas d'entrée. Sans cette
comparaison, un client qui republierait un formulaire inchangé remplirait
l'audit d'entrées vides, et l'historique deviendrait illisible là où il compte.

## 5. Transitions d'état

`WriteModelAudit::transition()` — ajouté par la Phase 9 — journalise un
changement d'état déjà appliqué, avec les seules colonnes touchées par la
transition :

```text
order_communication.queued     status + queued_at
order_communication.sent       status + sent_at + provider_message_id
order_communication.failed     status + failed_at + error_message
```

`provider_response` figure dans les colonnes posées mais est expurgée avant
écriture.

## 6. Le contexte d'audit

`AuditContext` porte trois informations, construites au seul endroit où la
couche HTTP est traduite (`BuildsAuditContext`) :

```text
organizationId   toujours renseigné
user             null pour les traitements système (Job, commande planifiée)
ipAddress        null hors requête HTTP
```

Un `user` nul n'est pas un défaut : c'est l'information exacte quand c'est le
système qui agit. `SendOrderCommunicationJob` et
`ProcessScheduledCommunications` s'auditent ainsi, sans emprunter l'identité de
l'utilisateur qui a mis la communication en file.

## 7. Lecture de l'audit

```text
GET /api/v1/audit-logs
```

Une seule route, en lecture seule, sous la permission `audit_logs.view` et
filtrée par l'organisation active. Aucune route de modification ni de
suppression n'existe : un journal qu'on peut réécrire n'en est pas un.

Filtres : `entityType`, `entityId`, `action`, `userId`, `createdFrom`,
`createdTo`.

## 8. Ce qui n'est pas audité, et pourquoi

| Non audité | Raison |
|---|---|
| Les lectures (`GET`) | Le diagramme ne prévoit pas de journal d'accès, et l'audit deviendrait plus volumineux que les données. |
| `stock_balances` | Produit par les mouvements, jamais écrit directement : le mouvement est l'événement, le solde en est la conséquence. |
| `order_number_sequences` | Table technique de numérotation, sans signification métier. |
| Les tables de liaison sans API propre | `user_roles`, `role_permissions` : auditées à travers leur parent (`role.updated`, `organization_user.updated`), avec la liste des rôles en `new_values`. |

# Phase 10 — Audit des données sensibles

Document exigé par le §20.

---

## 1. Résultat d'ensemble

```text
secrets exposés par une Resource       1  →  corrigé
secrets présents dans un audit         0
secrets écrits dans les logs           0
secrets dans une exception             0
secrets dans un snapshot               0
secrets dans une factory ou un seeder  0
```

## 2. Inventaire des données sensibles

| Donnée | Table | Protection | Restitution |
|---|---|---|---|
| Mot de passe utilisateur | `users.password` | **bcrypt**, 12 tours | Jamais. `#[Hidden]` sur le modèle. |
| Jeton de session | `personal_access_tokens.token` | **SHA-256** (Sanctum) | En clair une seule fois, à la connexion. |
| Jeton de réinitialisation | `password_reset_tokens.token` | hachage Laravel | Jamais restitué ; envoyé par e-mail. |
| Clé API client | `customer_api_configurations.api_key_hash` | **SHA-256**, `CHAR(64)` unique | En clair une seule fois, à l'émission et à la rotation. |
| Mot de passe de transport | `customer_export_configurations.encrypted_password` | **`Crypt` de Laravel** (réversible) | Jamais. Seul `hasPassword` est exposé. |
| Réponse fournisseur | `order_communications.provider_response` | — | **Liste blanche de clés** avant restitution. |
| Corps de communication | `order_communications.body` | — | Exposé au détail ; **expurgé de l'audit**. |
| Chemin de stockage | `documents.storage_path`, `export_jobs.storage_path` | — | **Jamais** depuis cette phase. |

## 3. La correction de cette phase

`ExportJobResource` exposait **`storagePath`** :

```php
'storagePath' => $this->storage_path,   // avant
'hasFile' => $this->storage_path !== null,   // après
```

Le §15 l'interdit nommément. `DocumentCompactResource` le masquait déjà depuis la
Phase 2, avec la bonne justification écrite en tête de fichier — les deux
ressources étaient donc en contradiction depuis la Phase 8.

Ce que le chemin révélait : l'arborescence du serveur de fichiers, la convention
de nommage des dépôts distants, et — pour un transport SFTP — la structure de
répertoires du client. `hasFile` répond à la seule question que l'appelant a
légitimement à poser.

Le test de la Phase 8 qui vérifiait qu'un chemin fourni par l'appelant était
ignoré a été conservé dans son intention : il vérifie désormais **en base** que
`storage_path` est resté nul, et que la clé `storagePath` est absente de la
réponse.

## 4. Vérifications par recherche

Recherche des huit termes du §20 dans l'ensemble du code :

| Terme | Occurrences dans `app/Http/Resources` | Nature |
|---|:-:|---|
| `password` | 1 | `hasPassword` — booléen, pas de valeur |
| `api_key` / `apiKeyHash` | 0 | — |
| `encrypted_password` / `encryptedPassword` | 0 | — |
| `token` | 0 | — |
| `secret` | 0 | — |
| `storage_path` / `storagePath` | 0 | — depuis cette phase |
| `authorization` | 0 | — |
| `providerResponse` | 1 | filtrée par liste blanche |

## 5. Expurgation dans l'audit

`WriteModelAudit` — hérité par `WriteConfigurationAudit` et
`WriteCommunicationAudit` — remplace les colonnes sensibles par `[secret]`
**avant** d'écrire dans `audit_logs` :

| Écrivain | Colonnes expurgées |
|---|---|
| `WriteConfigurationAudit` | `api_key_hash`, `encrypted_password` |
| `WriteCommunicationAudit` | `body`, `provider_response` |

Le journal se consulte plus largement que la table qu'il décrit : un
administrateur peut lire l'audit sans avoir le droit de lire les configurations
d'intégration. C'est ce qui rend l'expurgation nécessaire même pour une donnée
déjà chiffrée.

Trois tests le vérifient sur des valeurs témoins :

- la clé API en clair et son empreinte sont absentes de l'audit (Phase 8) ;
- le mot de passe de transport est absent de l'audit (Phase 8) ;
- le corps du message et la réponse fournisseur sont absents de l'audit
  (Phase 9).

## 6. Chiffrement et hachage — choix et raisons

| Donnée | Mécanisme | Raison |
|---|---|---|
| Mot de passe utilisateur | bcrypt | Choisi par l'utilisateur, donc à faible entropie : il faut un hachage **lent**. |
| Clé API client | SHA-256 | **Générée** par le serveur, 64 caractères aléatoires : la force brute est hors de portée, et la vérification doit être déterministe pour permettre l'index unique. C'est le raisonnement de Sanctum, déjà employé ici. |
| Mot de passe de transport | `Crypt` (AES) | **Réversible par nécessité** : le transport devra le présenter au serveur distant. Un hachage le rendrait inutilisable. |

## 7. Rotation

`POST /customer-api-configurations/{id}/rotate-key` remplace l'empreinte et
retourne la nouvelle clé une fois. **Aucun historique n'est conservé** : l'ancienne
empreinte disparaît, ce qui garantit qu'une clé révoquée ne peut plus
s'authentifier. Testé par `assertDatabaseMissing` sur l'ancienne empreinte.

La rotation exige sa propre permission, `customer_api_configurations.rotate_key`,
distincte d'`update` : renouveler une clé coupe l'accès des intégrations qui
l'utilisent.

## 8. Ce qui reste ouvert

| Point | État | Risque |
|---|---|---|
| `allowedIps` et `permissions` d'une clé API sont **stockés mais jamais appliqués** | Aucun middleware d'authentification par clé API n'existe. | **Aucun aujourd'hui** : la clé ne permet de s'authentifier nulle part. Devient critique le jour où ce middleware sera écrit — signalé au rapport de la Phase 8. |
| `.env.example` | Nettoyé par cette phase : les variables Redis et Memcached, inutilisées, ont été retirées. | Nul. Aucun secret réel n'y figure. |
| Fichiers | Stockage sur le disque `local` (privé). Téléchargement par route autorisée, jamais par URL directe. Nom de fichier validé contre la traversée de chemin. | Faible. |

# Exemples d'API — Phase 8 : intégrations clients

En-têtes communs :

```http
Authorization: Bearer <token>
X-Organization-Id: 01JABCDEFGHJKMNPQRSTVWXYZ
Content-Type: application/json
Accept: application/json
```

---

## Configurations d'import

### `POST /api/v1/customer-import-configurations`

Permission : `customer_import_configurations.create`.

```json
{
  "customerId": "01JC0000000000000000000C01",
  "name": "Import commandes SFTP",
  "sourceType": "sftp",
  "fileFormat": "csv",
  "mapping": {
    "orderNumber": "A",
    "customerReference": "B",
    "weight": "F"
  },
  "validationRules": {
    "orderNumber": "required",
    "weight": "numeric"
  },
  "isActive": true
}
```

`mapping` et `validationRules` sont des structures **librement
configurables** : le diagramme n'en définit pas le schéma, et le §9 interdit de
l'inventer. Elles sont validées comme tableaux, bornées en taille, et jamais
évaluées — ce sont des données, pas du code.

`sourceType` et `fileFormat` restent des chaînes : le §8 interdit d'en faire des
enums.

**Aucun endpoint d'upload ni d'exécution** : le diagramme définit une
configuration, pas un historique d'import.

---

## Accès API

### `POST /api/v1/customer-api-configurations`

Permission : `customer_api_configurations.create`.

```json
{
  "customerId": "01JC0000000000000000000C01",
  "name": "Portail client",
  "allowedIps": ["203.0.113.7", "198.51.100.0/24"],
  "permissions": ["orders.view", "orders.create", "tracking_events.view"],
  "isActive": true
}
```

**Ni `apiKey` ni `apiKeyHash` ne sont acceptés** : la clé est générée par le
serveur. Les fournir n'a aucun effet — un test le vérifie.

Réponse `201` — **la seule occasion de voir la clé** :

```json
{
  "data": {
    "configuration": {
      "id": "01JC0000000000000000000A01",
      "customerId": "01JC0000000000000000000C01",
      "name": "Portail client",
      "allowedIps": ["203.0.113.7", "198.51.100.0/24"],
      "permissions": ["orders.view", "orders.create", "tracking_events.view"],
      "isActive": true,
      "lastUsedAt": null
    },
    "apiKey": "xY3k…64 caractères…9Qz",
    "warning": "Cette clé n’est affichée qu’une seule fois : conservez-la maintenant, elle ne pourra pas être relue."
  },
  "meta": {}
}
```

Seule l'empreinte SHA-256 est stockée. Aucune lecture ultérieure ne restitue la
clé, et l'audit ne la contient pas — deux tests le vérifient.

### `POST /api/v1/customer-api-configurations/{configuration}/rotate-key`

Permission : `customer_api_configurations.rotate_key`, **distincte** d'`update` :
l'ancienne clé cesse de fonctionner à l'instant même.

Corps vide. Réponse identique à la création : nouvelle clé, une seule fois.

### Ce que valide `allowedIps`

Le §14 note que le diagramme ne précise pas la structure. **Retenue : liste plate
de chaînes**, chacune une adresse IP ou un bloc CIDR.

| Entrée | Résultat |
|--------|----------|
| `"203.0.113.7"` | acceptée |
| `"2001:db8::1"` | acceptée |
| `"198.51.100.0/24"` | acceptée |
| `"pas-une-ip"` | `422` |
| `"10.0.0.0/99"` | `422` — préfixe hors bornes |

### Ce que valide `permissions`

Codes existants uniquement, validés contre la table `permissions` : le §15
interdit un second système de permissions.

Cinq modules sont **interdits** à une clé client — `organizations`, `users`,
`roles`, `permissions`, `subscriptions` :

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": { "permissions.0": ["Cette permission est inconnue ou interdite à une clé API client."] }
}
```

Une intégration dépose des commandes et lit ses exports ; elle ne gère pas les
comptes du transporteur.

---

## Configurations d'export

### `POST /api/v1/customer-export-configurations` — SFTP

Permission : `customer_export_configurations.create`.

```json
{
  "customerId": "01JC0000000000000000000C01",
  "name": "Export commandes quotidien",
  "exportType": "orders",
  "format": "csv",
  "transport": "sftp",
  "host": "sftp.client.example",
  "port": 22,
  "username": "tricolis",
  "password": "motdepasse-du-client",
  "remoteDirectory": "/in",
  "fileNamePattern": "commandes-{date}.csv",
  "encoding": "UTF-8",
  "frequency": "daily",
  "isActive": true
}
```

Réponse `201` — **le mot de passe n'y figure pas** :

```json
{
  "data": {
    "id": "01JC0000000000000000000E01",
    "name": "Export commandes quotidien",
    "format": "csv",
    "transport": "sftp",
    "host": "sftp.client.example",
    "port": 22,
    "username": "tricolis",
    "hasPassword": true,
    "remoteDirectory": "/in",
    "fileNamePattern": "commandes-{date}.csv",
    "encoding": "UTF-8",
    "frequency": "daily",
    "isActive": true
  }
}
```

`hasPassword` est la seule information exposée : ni la forme claire, ni la forme
chiffrée ne sortent. Le mot de passe est chiffré par `Crypt` — réversible, car
le transport devra le présenter au serveur distant.

### `host` conditionnellement obligatoire

| Transport | `host` |
|-----------|--------|
| `ftp`, `sftp`, `rest_api` | **obligatoire** |
| `email`, `manual` | facultatif |

Les autres champs de connexion restent facultatifs partout : le §19 dit qu'ils
« **peuvent** » être nécessaires. Une connexion FTP anonyme, sur port par défaut,
à la racine, est un cas réel.

### `password` en modification — trois branches

| Payload | Effet |
|---------|-------|
| `password` **absent** | l'ancien est conservé |
| `"password": "nouveau"` | il remplace l'ancien |
| `"password": null` | il est effacé |

Sans la première branche, modifier le seul `username` effacerait le mot de passe.

### `fileNamePattern`

Validé contre la traversée de chemin et les caractères interdits. **Aucun moteur
de template** n'est créé — le §21 l'interdit, et le projet ne définit aucun
jeton. `{date}` ci-dessus n'est qu'une chaîne, pas une syntaxe reconnue.

| Motif | Résultat |
|-------|----------|
| `commandes-{date}.csv` | accepté |
| `../etc/passwd` | `422` |
| `sous/dossier.csv` | `422` |

### Suppression

`409` si la configuration a déjà produit des exports :

```json
{ "message": "Impossible de supprimer une configuration qui a déjà produit des exports." }
```

---

## Exports

### `POST /api/v1/export-jobs`

Permission : `export_jobs.create`.

```json
{
  "configurationId": "01JC0000000000000000000E01",
  "entityType": "order",
  "entityId": "01JC0000000000000000000O01",
  "status": "pending"
}
```

`customerId` **n'est pas accepté** : il est déduit de la configuration. Le §24
impose que les deux concordent — déduire est plus sûr que comparer.

Les champs de traitement — `fileName`, `storagePath`, `attemptCount`,
`generatedAt`, `sentAt`, `errorMessage` — ne sont pas acceptés non plus. Les
fournir n'a aucun effet ; un test le vérifie.

Une configuration **désactivée** est refusée :

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": { "configurationId": ["Cette configuration d’export est désactivée."] }
}
```

`entityType` n'accepte que des alias de la morph map — `order`, `invoice`,
`tour`… Un nom de classe PHP est refusé.

### `POST /api/v1/export-jobs/{exportJob}/retry`

Permission : `export_jobs.retry`.

```json
{ "status": "pending" }
```

Incrémente `attemptCount`, efface `errorMessage`. **Aucune colonne ajoutée**, le
§27 l'exige.

Un export déjà transmis n'est pas relançable :

```json
{ "message": "Cet export a déjà été transmis : le relancer produirait un doublon chez le client." }
```

### Pas de modification, pas de suppression

```http
PATCH  /api/v1/export-jobs/{exportJob}   → 405
DELETE /api/v1/export-jobs/{exportJob}   → 405
```

---

## Ce qui n'est pas livré, et pourquoi

**La génération de fichier et les transports ne sont pas implémentés.**

Le §30 est explicite : « Si les règles de contenu ne sont pas définies : […] **ne
pas inventer un schéma métier** […] **Ne pas produire de faux export métier.** »

Aucune règle de contenu n'existe : ni le diagramme, ni les Phases 1 à 7 ne
disent ce que contient un export XML de commandes, ni comment nommer ses
balises. Écrire un générateur reviendrait à inventer un format que le client
rejetterait.

Ce qui est livré est donc **la configuration et le suivi** : quelles données,
vers où, dans quel format, et l'historique de ce qui a été demandé. Ce qui
manque est **une seule chose, clairement délimitée** — le moteur qui lit un
`ExportJob`, produit le fichier et le dépose. Il s'ajoutera sans migration ni
changement d'API le jour où les formats seront spécifiés.

Concrètement : `POST /export-jobs` enregistre une demande réelle, mais
`generatedAt` et `sentAt` resteront nuls tant que ce moteur n'existe pas.

---

## Erreurs

| Statut | Cas |
|--------|-----|
| `401` | Jeton absent, expiré ou révoqué |
| `403` | Permission manquante, ou en-tête `X-Organization-Id` absent |
| `404` | Ressource d'un client d'une autre organisation |
| `405` | `PATCH`/`DELETE` sur un export |
| `409` | Configuration ayant produit des exports, export déjà transmis |
| `422` | Périmètre, nom dupliqué, IP malformée, permission interdite, format ou transport hors enum, `host` manquant, motif de fichier dangereux |

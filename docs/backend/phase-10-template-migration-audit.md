# Audit de la migration Template — Phase 10

Vérification du §28. Rien n'est déclaré sur la foi du code : chaque ligne
correspond à une exécution réelle, sur la base de test le 2 septembre 2026.

## 1. Migration depuis zéro

```bash
php artisan migrate:fresh --env=testing --force
```

Les 34 migrations passent, dont les cinq de la Phase 9 et 10. État d'arrivée :

| Vérification | Résultat |
|---|---|
| Table `templates` | **présente** |
| Table `communication_templates` | **absente** |
| Table `invoice_templates` | **absente** |

Colonnes de `templates`, dans l'ordre réel :

```text
id  organization_id  customer_id  service_id  code  name  channel
template_type  subject_template  body_template  body_format  language
available_variables  is_default  is_active  created_at  updated_at
```

Conforme au modèle du §11, `body_format` en plus — colonne ajoutée en Phase 8,
conservée et documentée.

## 2. Chemin de mise à niveau, avec des données

Le §28 demande de tester l'*upgrade*, pas seulement la création. Procédure
réellement exécutée :

```text
1. migrate:rollback --step=4   → retour à communication_templates
2. écriture d'un modèle, et d'une règle qui le référence
3. migrate                     → unification
4. relecture
```

| Vérification | Résultat |
|---|---|
| Modèle retrouvé, **même identifiant** | oui |
| `code` conservé | `UPGRADE_CHECK` |
| `body_template` conservé | `Corps` |
| `customer_id` ajouté, à nul | oui |
| `communication_rules.template_id` intacte | **oui** |
| Ancienne table subsistante | non |

C'est la garantie que le §0.3 de la Phase 9 réclamait : le renommage conserve
les identifiants, et MySQL réoriente lui-même les clés étrangères. **Aucune
communication historique n'est perdue, aucun doublon n'est créé.**

Les permissions ne sont pas mesurables sur une base fraîchement migrée sans
semis — elles y sont à zéro avant comme après. Le renommage
`communication_templates.* → templates.*` a été vérifié séparément sur la base
de développement : quatre permissions, code **et** module mis à jour, rôles
préservés puisque `role_permissions` pointe sur l'identifiant.

## 3. Index — un défaut trouvé et corrigé

`RENAME TABLE` déplace les index avec la table mais **garde leurs noms**. Neuf
index de `templates` s'appelaient encore `communication_templates_*` :

```text
communication_templates_organization_id_code_unique
communication_templates_organization_id_index
communication_templates_service_id_index
communication_templates_channel_index
communication_templates_template_type_index
communication_templates_language_index
communication_templates_is_default_index
communication_templates_is_active_index
communication_templates_created_at_index
```

Rien n'en souffrait — un index sert par ses colonnes, pas par son nom — mais qui
inspecte le schéma y lit une table qui n'existe plus, et se demande s'il en
reste deux. Le §28 demande des *« indexes adaptés »*.

`2026_09_02_100000_rename_template_indexes` les renomme. Renommer un index ne le
reconstruit pas : MySQL réécrit une entrée de dictionnaire. L'opération est
conditionnée à l'existence de l'ancien nom, sans quoi elle échouerait sur une
base créée après le renommage, où Laravel a déjà nommé correctement.

État après :

```text
PRIMARY                              templates_language_index
templates_organization_id_code_unique  templates_is_default_index
templates_organization_id_index      templates_is_active_index
templates_service_id_index           templates_created_at_index
templates_channel_index              templates_customer_id_index
templates_template_type_index
```

**0 index au nom obsolète.**

## 4. Faut-il un index composite pour la résolution ?

Le §29 demande d'analyser les index autour de la résolution de modèle, et
interdit *« l'index géant arbitraire »*. `ResolveTemplateAction` filtre sur :

```sql
organization_id = ? AND template_type = ? AND is_active = 1
AND channel <=> ? AND (customer_id IS NULL OR customer_id = ?)
AND (service_id IS NULL OR service_id = ?)
```

Un index `(organization_id, template_type, is_active)` servirait mieux qu'un
index par colonne. **Il n'est pourtant pas ajouté**, pour une raison mesurable :
une organisation compte des **dizaines** de modèles, pas des millions. MySQL
lira l'index sur `organization_id`, ramènera quelques dizaines de lignes et
filtrera le reste en mémoire — plus vite qu'il ne parcourrait un index composite
plus large.

Ajouter cet index aujourd'hui coûterait de l'écriture à chaque modification
pour un gain non mesurable, ce que le §29 nomme précisément. La décision se
reverra si une organisation dépasse le millier de modèles.

## 5. Recherche finale de résidus

| Recherche | Attendu | Trouvé |
|---|---|---|
| Modèle `CommunicationTemplate` en runtime | 0 | **0** |
| Table `communication_templates` en runtime | 0 | **0** |
| `InvoiceTemplate` | 0 | **0** |
| `communication-templates` en route | 0 | **0** |
| `communicationTemplateKeys` | 0 | **0** |

Les occurrences restantes sont exactement celles que le §15 autorise :

| Fichier | Nature |
|---|---|
| `2026_08_07_100001_create_communication_templates_table.php` | migration historique — décrit l'état d'où l'on part |
| `2026_08_25_100000_add_body_format_...php` | migration historique |
| `2026_09_01_100001_rename_...php` | la migration de renommage elle-même |
| `2026_09_01_100002_rename_..._permissions.php` | idem, pour les permissions |
| `2026_09_01_100004_rename_..._morph_alias.php` | idem, pour l'alias d'audit |
| `TemplatePolicy.php`, `CreateOrderCommunicationDialog.tsx` | commentaires expliquant le renommage |

Les migrations d'origine ne sont **pas** réécrites, et c'est délibéré : elles
décrivent l'état d'où part une installation existante. Les modifier casserait
une installation neuve, où le renommage s'exécute après elles.

## 6. Conclusion

```text
migration depuis zéro      verte
chemin de mise à niveau    vérifié avec données, identifiants et FK intacts
index                      renommés, 0 nom obsolète
index composite            analysé, écarté avec sa raison
résidus                    0 en runtime
102 tests Templates + Hardening   verts
```

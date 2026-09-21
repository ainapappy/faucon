# Ajouter un type d'intégration

Une **intégration** est une connexion nommée avec credentials chiffrés, réutilisable par les
nodes d'action. Deux types existent (`generic_http`, `smtp`) — voici le chemin pour en ajouter
un troisième (ex. un SaaS à API propriétaire).

## Vue d'ensemble

```text
Enum IntegrationType  →  Form Request (whitelist credentials)  →  ConnectionTester
        ↓                        (le cast encrypted:array fait le reste)
Front : lib/integrationTypes.ts (miroir du schéma de formulaire) + dialog champs conditionnels
Consommateurs : handlers d'action via IntegrationResolver
```

## Étapes backend

### 1. L'enum — `app/Enums/IntegrationType.php`

Ajoutez la case avec son libellé FR : `case ApiXyz = 'api_xyz';`

### 2. La whitelist de credentials — `app/Http/Requests/Integrations/`

Le Form Request de création/mise à jour valide les credentials **par type** (champ attendu,
bornes, messages FR). C'est le garde S5 de la phase 11 : un champ inattendu est **refusé**, pas
stocké silencieusement. Ajoutez le schéma de votre type — et mettez à jour
`tests/Feature/Security/IntegrationCredentialsWhitelistTest.php` **d'abord** (TDD : le test rouge
qui prouve que le champ inconnu est rejeté).

### 3. Le testeur de connexion — `app/Services/Integration/ConnectionTester.php`

Un cas dans le switch : un appel réel mais anodin (ping authentifié) qui enregistre
`last_tested_at` / `last_test_succeeded` et renvoie un message FR **sans jamais refléter le
credential**. Regardez le chemin SMTP (`SmtpTransportFactory`) pour un exemple non-HTTP.

### 4. Le consommateur — dans le handler d'action concerné

Les handlers résolvent l'intégration par `IntegrationResolver` (résolution par PK au runtime ;
le scoping équipe est garanti **à la sauvegarde**, jamais re-vérifié au vol). Le credential est
lu depuis le tableau déchiffré par le cast — puis utilisé, jamais loggé.

## Étapes frontend

### 5. Le miroir de formulaire — `resources/js/lib/integrationTypes.ts`

Le module est le **miroir documenté** de la validation backend : mêmes bornes, mêmes clés
d'erreur (`credentials.<champ>`), mêmes messages FR (apostrophes typographiques comprises). Le
dialog d'édition (`IntegrationFormDialog`) en dérive : champs conditionnels par type, secrets
jamais re-présentés après stockage (vide = inchangé côté backend).

### 6. Tests front si la logique s'y prête

Le schéma de formulaire et ses transitions (création vs édition) sont testés dans les specs du
module — suivez le pattern existant.

## Garanties à ne pas casser

| Garantie                               | Où elle vit                                   |
| -------------------------------------- | --------------------------------------------- |
| Credentials chiffrés au repos          | cast `encrypted:array` (`Integration`)        |
| Champ credential inattendu refusé (S5) | Form Request + test whitelist                 |
| Secret jamais renvoyé au front         | `IntegrationSummaries` (résumés non secrets)  |
| Secret jamais dans les logs/messages   | redaction à l'écriture + messages FR neutres  |
| Accès équipe                           | `IntegrationPolicy` + scoping à la sauvegarde |

## Vérification finale

```bash
vendor/bin/pint --dirty --format agent
composer types:check
php artisan test --compact          # whitelist, policies, tester, handler consommateur
npm run test:unit                   # si integrationTypes.ts a changé
```

- [ ] case d'enum + libellé FR
- [ ] whitelist credentials testée (rouge d'abord)
- [ ] tester de connexion sans fuite de credential
- [ ] miroir front synchronisé (bornes + messages identiques)
- [ ] doc [Intégrations](../../usage/integrations.md) mise à jour (tableau des types)

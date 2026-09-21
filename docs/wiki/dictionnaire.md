# 📖 Dictionnaire des termes techniques

Le vocabulaire de Faucon, défini une fois pour toutes. Chaque définition est écrite **dans le
contexte de ce projet** — pour la définition générale d'une technologie (Laravel, Vue…),
voyez sa documentation officielle.

> Les entrées pointent vers la page qui détaille le concept : **[U]** doc
> [d'utilisation](../usage/README.md) · **[T]** doc [technique](../technical/README.md).

## Index thématique

- **Domaine workflow** : Workflow · Node · Type de node · Catégorie · Catalogue · Edge · Handle ·
  Graphe · Trigger · Handler · Registre · Exécution · Test-run · Traversal · Branche · Skipped ·
  Fail-fast · Contexte d'exécution · Variable · Interpolation · Dot-path · Cycle
- **Infrastructure d'exécution** : Queue · Job · Worker · Retry · Backoff · Timeout · Cancellation ·
  Rate limiting · Budget de requêtes
- **IA** : Provider · Driver · Mode IA · Structured output · Usage (tokens) · Fake provider
- **Intégrations & webhooks** : Intégration · Credential · Chiffrement au repos · Webhook ·
  Endpoint · Idempotence · SSRF · DNS pinning · Payload · Redaction · Rétention
- **Produit** : Builder · Dashboard · KPI · Template · Snapshot · Publication · Duplication ·
  Galerie · Notification in-app · Timeline
- **Équipes & autorisation** : Équipe · Team-scoped · Policy · Permission · Rôle · Personal team
- **Stack & architecture** : Monolithe · Inertia · Prop · Prop différée · Page · Wayfinder ·
  Vite · SSR · Form Request · Action · Service · DTO · Enum · Cast · Middleware ·
  Soft delete · Prunable · Factory · Seeder · Migration
- **Qualité & sécurité** : TDD · Pest · Vitest · PHPStan/Larastan · Pint · CI · N+1 ·
  Fortify · 2FA TOTP · Passkey · CSRF · Reverb · Echo

---

## A

**Action (classe `app/Actions/`)** — [T] Classe d'intention applicative : encapsule une opération
métier déclenchable depuis un contrôleur ou un job (ex. `StartWorkflowRun`, `SaveWorkflowGraph`).
Convention du projet : l'orchestration vit dans les Actions, la logique réutilisable dans les
Services.

**AI (modes)** — voir **Mode IA**.

**Annulation** — voir **Cancellation**.

**API (routes `routes/api.php`)** — [T] Quasi absente du projet : Faucon est un monolithe Inertia,
la communication passe par des visites de pages. Seule `GET /user` existe (Sanctum), par
convention du starter.

**Aperçu de template** — [U] Miniature SVG statique du graphe d'un template dans la galerie,
calculée depuis un payload aminci (les configs de nodes ne quittent jamais le serveur).

---

## B

**Backoff** — [T] Délai d'attente avant une nouvelle tentative d'un job en queue (config
`WORKFLOW_EXECUTION_MAX_TRIES` / backoff, défaut : 1 retry après 30 s).

**Budget de requêtes** — [T] Nombre maximal de requêtes SQL épinglé par test pour une page ou une
prop (ex. `workflows.index` = 3, quel que soit le volume). La preuve anti-N+1 : le budget doit
rester constant quand la fixture passe de 5 à 15 lignes. Voir [tests-et-qualite](../technical/tests-et-qualite.md).

**Builder** — [U] L'éditeur visuel de workflows ([builder.html](../../.knowledge/design/builder.html)
est la maquette de référence) : canvas, palette de nodes, inspecteur, sauvegarde transactionnelle.

---

## C

**Cancellation** — [T] Arrêt demandé d'une exécution en cours : un flag en cache vérifié
**entre deux nodes** (hook `$beforeNode` du runner). Un node démarré va au bout ; le suivant est
refusé. Statut final : `cancelled`.

**Cast (chiffré)** — [T] Conversion automatique Eloquent d'une colonne : `credentials` sur
`Integration` utilise `encrypted:array` (chiffré au repos), `token` sur `WebhookEndpoint` est
`encrypted`. Conséquence : une colonne chiffrée **n'est pas requêtable** — on indexe un hash à la
place (voir **Token hash**).

**Catalogue (NodeCatalog)** — [T] La classe `NodeCatalog` : la liste des **17 types de nodes**
connus de Faucon, source de vérité partagée par le backend et exposée au front en prop. À ne pas
confondre avec le **Registre** (les types réellement exécutables).

**Catégorie** — [U] Groupe d'appartenance d'un type de node, à ordre et couleur fixes (design
validé CVD) : **Triggers** (ambre), **Data** (bleu), **Logique** (fuchsia), **IA** (émeraude),
**Actions** (violet). Jamais la couleur seule : toujours icône + libellé.

**CI** — [T] Intégration continue : le workflow GitHub Actions `.github/workflows/tests.yml` lance
`composer ci:check` à chaque push sur `main` et à chaque PR (checks front, PHPStan, suite Pest).

**Credential** — [U] Identifiant d'accès stocké dans une **Intégration** (clé API, mot de passe
SMTP…). Chiffré en base, jamais renvoyé au front, jamais écrit dans les logs.

**CSRF** — [T] Protection standard Laravel par token de session. Exemption consciente et unique :
les routes `webhooks/*` (appelées par des machines externes, authentifiées par token).

**Cycle** — [T] Boucle dans le graphe (A → B → A). Détecté par le `GraphValidator` (DFS) ; un
graphe cyclique n'est ni sauvegardable ni exécutable.

---

## D

**Dashboard** — [U] Page d'accueil d'une équipe : KPIs, volume d'exécutions sur 30 jours, top
workflows, exécutions récentes.

**Deux facteurs** — voir **2FA TOTP**.

**Dot-path** — [T] Chemin pointé dans les variables d'un run (`trigger.email`, `classification.label`).
Résolu par un parseur à charset fermé : jamais d'`eval`, jamais de méthode ou propriété arbitraire.

**Driver** — [T] Implémentation concrète d'une abstraction (ex. drivers du `AiProviderManager` :
`fake`, `openai`, `anthropic`, `zai`).

**Duplication** — [U] Copie d'un workflow existant (ou d'un template en workflow) : le graphe est
reproduit à l'identique sous un nouveau nom, dans l'équipe courante.

---

## E

**Edge** — [U] Une flèche du graphe : relie deux **nodes** et définit le flux de données. Stockée
dans `workflow_edges` (source, cible, handle optionnel).

**Équipe** — [U] Le périmètre de partage de Faucon : workflows, intégrations, templates, dashboard
et exécutions appartiennent à une équipe, jamais à un utilisateur isolé.

**Endpoint** — [T] URL d'un service (ex. l'endpoint webhook public `POST /webhooks/{token}`).

**Exécution (run)** — [U] Une occurrence d'un workflow qui tourne. Persistée dans
`workflow_executions` avec son statut
(`pending → running → completed | failed | cancelled`), son input, son résultat, ses logs.

**Exécutabilité** — [T] La propriété « ce graphe peut tourner » : exactement un trigger, types
connus **avec handler enregistré**, configs valides, pas de cycle. Vérifiée à la demande
(`WorkflowValidator`) et réclamée à l'activation du workflow.

---

## F

**Fake provider** — [T] Le driver IA `fake` (défaut) : réponses déterministes sans réseau ni clé.
Il rend les tests IA possibles et la démo fonctionnelle sans compte OpenAI/Anthropic.

**Fail-fast** — [T] Comportement du runner : la première erreur de node arrête l'exécution
(statut `failed`), sauf node configuré `failure_policy: continue`.

**Factory** — [T] Générateur de fixtures de test (`database/factories/`, 11 factories avec états
nommés : `WorkflowExecutionFactory::failed()`, `WorkflowNodeFactory::ofType(…)`…).

**Fortify** — [T] Le backend d'authentification headless de Laravel : login, registration,
vérification email, **2FA TOTP**, **passkeys**. Faucon n'a que des pages Vue custom par-dessus.

**Form Request** — [T] Classe de validation HTTP (`app/Http/Requests/`) : valide et autorise la
requête avant le contrôleur.

---

## G

**Galerie** — [U] La page des **Templates** : templates système (publics) et d'équipe, filtrables,
avec aperçu du graphe et bouton « Utiliser ».

**Graphe** — [U] La structure d'un workflow : des **nodes** reliés par des **edges**, partant
d'un **trigger**. Dirigé (le flux a un sens) et acyclique (pas de boucle).

---

## H

**Handle** — [T] Le point de sortie typé d'un node (ex. les branches `true`/`false` d'une
**Condition**). Un edge se branche sur un handle précis ; la validation refuse les handles
inconnus pour le type.

**Handler** — [T] La classe qui exécute un type de node : implémente `NodeHandler`
(`type()`, `validate()`, `execute()`). Convention de nommage : `trigger.manual` →
`Handlers\Trigger\ManualHandler`.

---

## I

**Idempotence (webhook)** — [T] Propriété « rejouer le même appel ne crée pas un second run » :
mise en œuvre par l'en-tête `X-Request-Id`, dédupliqué par token sur une fenêtre glissante
(`WebhookRequest`, 1 j).

**Inertia** — [T] La couche de communication du monolithe : le backend rend des « pages » (nom +
props), Vue les affiche sans API REST. Version 3.

**Interpolation** — [U] Le mécanisme `{{ chemin }}` qui injecte des données d'un node dans la
config d'un autre (ex. `{{ trigger.email }}` dans le destinataire d'un e-mail). Échappement :
`@{{ }}` pour un littéral.

**Intégration** — [U] Une connexion nommée à un service externe (HTTP générique, SMTP), avec ses
**credentials** chiffrés, réutilisable par les nodes d'action.

---

## J

**Job** — [T] Une tâche différée exécutée par un **worker**. `RunWorkflowJob` exécute une
exécution persistée : transitions de statut, hooks, logs, événements.

---

## K

**KPI** — [U] Indicateur du **Dashboard** : exécutions totales, taux de succès, durée moyenne,
volume 30 jours — agrégés par requêtes SQL, pas en PHP.

---

## M

**Middleware** — [T] Filtre HTTP (ex. `EnsureTeamMembership` refuse l'accès aux routes d'une
équipe dont on n'est pas membre ; `SetSecurityHeaders` ajoute les en-têtes de sécurité).

**Migration** — [T] Le versionnage du schéma de base de données (`database/migrations/`).

**Mode IA** — [U] Le paramètre d'un node IA qui choisit ce qu'il fait : `prompt`,
`classification`, `extraction`, `summarization`, `generation`. Un seul handler (`AiNodeHandler`)
paramétré par un enum `AiMode`.

**Monolithe** — [T] Une seule application (Laravel) qui sert les pages et la logique, sans
backend séparé ni API publique.

---

## N

**N+1** — [T] L'anti-pattern « une requête par élément de liste » (1 + N requêtes). Traqué par
eager loading et **prouvé absent** par les tests de **budget de requêtes**.

**Node** — [U] Une étape du graphe, avec un type, un nom, une config et une position. 17 types au
catalogue, répartis en 5 **catégories** — voir la [référence des nodes](../usage/nodes.md).

**Notification in-app** — [U] La cloche du shell : l'auteur d'un run reçoit une notification si
son exécution échoue. Lues via l'API notifications (marquage idempotent).

---

## P

**Passkey** — [U] Authentification par clé biométrique/materialisée (WebAuthn), gérée dans
Réglages → Sécurité via Fortify.

**Payload** — [T] Les données sérialisées envoyées au navigateur (props Inertia) ou reçues par
un webhook (plafonné à 64 Kio, profondeur JSON 10).

**Personal team** — [T] L'équipe personnelle créée avec chaque compte : l'espace privé par
défaut, non quittable, non supprimable.

**Policy** — [T] La classe d'autorisation d'un modèle (`WorkflowPolicy`, `IntegrationPolicy`…).
Toutes les policies métier sont **team-scoped** et délèguent aux **permissions** de rôle.

**Permission** — [T] Une capacité nommée `domaine:action` (`workflow:create`, `integration:update`…,
13 au total), accordée par **rôle** via `TeamPermission`.

**Prunable (rétention)** — [T] Les tables volubiles se purgent : exécutions terminées 90 j, logs
30 j, requêtes webhook 1 j — via `MassPrunable` et un `model:prune` quotidien planifié.

**Prop** — [T] Une donnée passée d'un contrôleur à une page Inertia (ex. `nodeTypes`, `graph`,
`execution.logs`). Règle du projet : **jamais de secret dans une prop**.

**Provider (IA)** — [T] Le service d'IA derrière un node IA, via le contrat `AiProvider` :
OpenAI, Anthropic, Z.ai ou le **fake provider**.

---

## Q

**Queue** — [T] La file des **jobs** (pilote `database` par défaut) : les exécutions partent en
queue, les workers les consomment. En test : `sync`.

---

## R

**Rate limiting** — [T] Les plafonds d'appel : webhook 60/min/token, lancement de run
10/min/utilisateur+équipe, appels IA 30/min/équipe (config `workflows.rate_limits`).

**Redaction** — [T] Le masquage des secrets **à l'écriture** des logs (`SecretRedactor` par clés :
`password`, `token`, `authorization`…), avec bornage des valeurs (2 000 caractères, 64 Kio JSON).

**Registre (NodeHandlerRegistry)** — [T] La table `type → handler` remplie explicitement dans
`AppServiceProvider`. **L'unique point d'extension du moteur** : un type sans handler est
détecté à la validation (`handler_missing`) et rend le graphe non exécutable.

**Rétention** — voir **Prunable**.

**Rôle** — [U] Le niveau d'un membre d'équipe : `owner`, `admin`, `member` — chacun avec ses
**permissions**.

**Résumé** — Mode IA `ai.summarization` : condenser un contenu (voir [nodes](../usage/nodes.md)).

**Retry** — [T] La nouvelle tentative d'une exécution échouée, **par raison** : seules certaines
causes (réseau, timeout IA) sont rejouables (config `retryable_reasons`).

---

## S

**Seeder** — [T] Le peuplement de la base (`database/seeders/`) : compte démo, 3 workflows prêts,
historique d'exécutions, intégrations d'exemple, 3 templates système.

**SSR** — [T] Server-Side Rendering : le premier rendu des pages Inertia est fait côté serveur
(`npm run build:ssr`).

**SSRF** — [T] Server-Side Request Forgery : l'attaque où un utilisateur fait appeler par le
serveur une cible interne. Garde dans `HttpClient` : plages IP bloquées, **DNS pinning**,
redirections revalidées hop par hop, plafond de réponse.

**Structured output** — [T] La garantie qu'un node IA reçoit du **JSON valide contre un schéma**
imposé par `AiCompleter` : instruction de format, validation, un retry de rappel, puis erreur
typée. Voir [fournisseurs-ia](../technical/fournisseurs-ia.md).

---

## T

**Team-scoped** — [T] Le patron d'appartenance : toute ressource métier porte un `team_id` et
son accès passe par la Policy de l'équipe. Le cœur du modèle de sécurité.

**Template** — [U] Un graphe figé en modèle : **système** (public, seedé) ou **d'équipe**
(publish depuis un workflow). « Utiliser » crée un workflow neuf dans votre équipe.

**Test-run** — [U] L'exécution **synchrone** de test depuis le builder : résultats node par node
sans persister d'exécution. Permission `workflow:update`.

**Timeline** — [U] La vue d'une exécution dans la page Exécutions : les logs node par node
(statuts, durées, inputs/outputs masqués, erreurs).

**Token** — [T] Ici : le secret d'URL d'un **webhook** (`{token}` dans `POST /webhooks/{token}`),
chiffré en base, adressable uniquement par son hash.

**Token hash** — [T] L'empreinte SHA-256 d'un token, indexée en base pour la recherche — car une
colonne **chiffrée n'est pas requêtable**.

**Trigger** — [U] Le node de départ d'un graphe (un seul par workflow) : **Manuel**, **Webhook**
ou **Planifié** (cron).

**2FA TOTP** — [U] La double authentification par code à durée limitée (application
d'authentification), gérée dans Réglages → Sécurité.

---

## V

**Variable** — [U] Une donnée produite pendant un run (sortie d'un node, input du trigger),
adressable par **interpolation** `{{ node.cle.chemin }}`.

---

## W

**Wayfinder** — [T] Le générateur de fonctions TypeScript pour les routes Laravel : le front
appelle `StoreWorkflowController()` au lieu d'écrire des URLs à la main. Régénéré par
`php artisan wayfinder:generate`.

**Webhook** — [U] Le déclencheur « pousse-moi des données » : une URL publique par workflow,
protégée par token, idempotente et rate-limitée ; répond 202 et délègue à la queue.

**Worker** — [T] Le process qui consomme la **queue** (`composer dev` le lance en local).

---

_Un terme vous manque ? Il est peut-être défini dans la [doc d'utilisation](../usage/README.md)
ou la [doc technique](../technical/README.md) — sinon, c'est une entrée à ajouter ici._
